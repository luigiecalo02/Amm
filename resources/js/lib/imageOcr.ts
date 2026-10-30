type ProgressFn = (percent: number) => void;

let workerPromise: Promise<any> | null = null;
let progressCb: ProgressFn | null = null;

const getWorker = async () => {
    if (!workerPromise) {
        workerPromise = (async () => {
            const { createWorker } = await import('tesseract.js');

            try {
                return await createWorker('spa', 1, {
                    logger: (message: { status?: string; progress?: number }) => {
                        if (message.status === 'recognizing text' && progressCb) {
                            progressCb(Math.round((message.progress ?? 0) * 100));
                        }
                    },
                });
            } catch {
                return createWorker('eng', 1, {
                    logger: (message: { status?: string; progress?: number }) => {
                        if (message.status === 'recognizing text' && progressCb) {
                            progressCb(Math.round((message.progress ?? 0) * 100));
                        }
                    },
                });
            }
        })();
    }

    return workerPromise;
};

const prepareImage = async (file: File): Promise<Blob> => {
    const bitmap = await createImageBitmap(file);
    const minSide = Math.min(bitmap.width, bitmap.height);
    const maxSide = Math.max(bitmap.width, bitmap.height);
    let scale = minSide > 0 && minSide < 1100 ? 1100 / minSide : 1;

    if (maxSide * scale > 2200) {
        scale = 2200 / maxSide;
    }

    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(bitmap.width * scale));
    canvas.height = Math.max(1, Math.round(bitmap.height * scale));
    const context = canvas.getContext('2d');

    if (!context) {
        bitmap.close();
        return file;
    }

    context.filter = 'grayscale(1) contrast(1.4) brightness(1.05)';
    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();

    return await new Promise((resolve) => {
        canvas.toBlob((blob) => resolve(blob ?? file), 'image/png');
    });
};

export const extractTextFromImage = async (file: File, onProgress?: ProgressFn): Promise<string> => {
    if (!file.type.startsWith('image/')) {
        return '';
    }

    progressCb = onProgress ?? null;
    onProgress?.(5);

    try {
        const prepared = await prepareImage(file);
        onProgress?.(15);
        const worker = await getWorker();
        const { data } = await worker.recognize(prepared);

        return String(data.text ?? '').trim();
    } catch {
        return '';
    } finally {
        progressCb = null;
        onProgress?.(100);
    }
};
