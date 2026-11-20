type ProgressFn = (percent: number, label: string) => void;

type Reader = {
    recognize: (image: Blob, options: Record<string, never>, output: { text: boolean }) => Promise<{ data?: { text?: string } }>;
    setParameters: (params: Record<string, string>) => Promise<unknown>;
    terminate: () => Promise<unknown>;
};

const STAGES: Record<string, { from: number; to: number; label: string }> = {
    'loading tesseract core': { from: 12, to: 32, label: 'Cargando el lector…' },
    'initializing tesseract': { from: 32, to: 42, label: 'Cargando el lector…' },
    'loading language traineddata': { from: 42, to: 62, label: 'Cargando el idioma…' },
    'initializing api': { from: 62, to: 70, label: 'Preparando la lectura…' },
    'recognizing text': { from: 70, to: 96, label: 'Leyendo el texto…' },
};

const WORKER_MS = 25000;
const READ_MS = 20000;

let worker: Reader | null = null;
let starting: Promise<Reader | null> | null = null;
let session = 0;
let progressCb: ProgressFn | null = null;
let cancelWait: (() => void) | null = null;
let creepTimer: number | null = null;
let shownPercent = 0;

class ReadCancelled extends Error {}

class ReadTimeout extends Error {}

const report = (percent: number, label: string) => {
    shownPercent = percent;
    progressCb?.(percent, label);
};

const stopCreep = () => {
    if (creepTimer !== null) {
        window.clearInterval(creepTimer);
        creepTimer = null;
    }
};

const startCreep = (ceiling: number, label: string) => {
    stopCreep();
    creepTimer = window.setInterval(() => {
        if (shownPercent >= ceiling) {
            return;
        }

        report(Math.min(ceiling, shownPercent + 1), label);
    }, 450);
};

const withTimeout = <T>(promise: Promise<T>, ms: number): Promise<T> => new Promise((resolve, reject) => {
    const timer = window.setTimeout(() => reject(new ReadTimeout()), ms);
    promise.then(
        (value) => {
            window.clearTimeout(timer);
            resolve(value);
        },
        (error) => {
            window.clearTimeout(timer);
            reject(error);
        },
    );
});

const untilCancel = () => new Promise<never>((_, reject) => {
    cancelWait = () => reject(new ReadCancelled());
});

const raceRead = <T>(promise: Promise<T>) => Promise.race([promise, untilCancel()]);

const noteStage = (message: { status?: string; progress?: number }) => {
    const stage = STAGES[message.status ?? ''];

    if (!stage) {
        return;
    }

    const ratio = Math.min(1, Math.max(0, message.progress ?? 0));
    const percent = Math.round(stage.from + (stage.to - stage.from) * ratio);

    if (percent >= shownPercent) {
        report(percent, stage.label);
    }
};

const createReader = async (lang: string): Promise<Reader> => {
    const { createWorker } = await import('tesseract.js');
    const pending = createWorker(lang, 1, {
        logger: (message: { status?: string; progress?: number }) => noteStage(message),
        errorHandler: () => undefined,
    });

    try {
        const created = await withTimeout(pending, WORKER_MS) as Reader;
        await withTimeout(created.setParameters({
            tessedit_pageseg_mode: '6',
            user_defined_dpi: '300',
        }), 5000);

        return created;
    } catch (error) {
        pending.then((created) => (created as Reader).terminate()).catch(() => undefined);
        throw error;
    }
};

const dropWorker = () => {
    session += 1;
    const current = worker;
    const pending = starting;
    worker = null;
    starting = null;
    void current?.terminate();
    pending?.then((created) => created?.terminate()).catch(() => undefined);
};

const getWorker = (): Promise<Reader | null> => {
    if (worker) {
        return Promise.resolve(worker);
    }

    if (starting) {
        return starting;
    }

    const sessionAtStart = session;
    starting = (async () => {
        try {
            const created = await createReader('spa');

            if (session !== sessionAtStart) {
                await created.terminate();

                return null;
            }

            worker = created;

            return created;
        } catch {
            return null;
        }
    })().finally(() => {
        if (session === sessionAtStart) {
            starting = null;
        }
    });

    return starting;
};

const prepareImage = async (file: File): Promise<Blob> => {
    const bitmap = await createImageBitmap(file);
    const maxSide = Math.max(bitmap.width, bitmap.height);
    const scale = maxSide > 1400 ? 1400 / maxSide : 1;
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(bitmap.width * scale));
    canvas.height = Math.max(1, Math.round(bitmap.height * scale));
    const context = canvas.getContext('2d');

    if (!context) {
        bitmap.close();

        return file;
    }

    context.filter = 'grayscale(1) contrast(1.25)';
    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();

    return await new Promise((resolve) => {
        canvas.toBlob((blob) => resolve(blob ?? file), 'image/jpeg', 0.85);
    });
};

export const warmupImageReader = () => {
    void getWorker();
};

export const cancelImageRead = () => {
    cancelWait?.();
    dropWorker();
};

export const extractTextFromImage = async (file: File, onProgress?: ProgressFn): Promise<string> => {
    if (!file.type.startsWith('image/')) {
        return '';
    }

    progressCb = onProgress ?? null;
    shownPercent = 0;
    report(6, 'Preparando la foto…');
    startCreep(40, 'Cargando el lector…');

    try {
        const prepared = await raceRead(prepareImage(file));
        report(Math.max(shownPercent, 10), 'Cargando el lector…');
        startCreep(68, 'Cargando el idioma…');
        const reader = await raceRead(getWorker());

        if (!reader) {
            return '';
        }

        stopCreep();
        report(Math.max(shownPercent, 72), 'Leyendo el texto…');
        startCreep(94, 'Leyendo el texto…');
        const result = await raceRead(withTimeout(
            reader.recognize(prepared, {}, { text: true }),
            READ_MS,
        ));

        return String(result.data?.text ?? '').trim();
    } catch (error) {
        if (error instanceof ReadTimeout) {
            dropWorker();
        }

        return '';
    } finally {
        stopCreep();
        cancelWait = null;
        progressCb = null;
        onProgress?.(100, 'Listo');
    }
};
