<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import DocumentKindIcon from '@/components/DocumentKindIcon.vue';

const FRAME_RATIOS: Record<string, number> = {
    'id-card': 85.6 / 54,
    portrait: 3 / 4,
    sheet: 1.414,
};

const props = defineProps<{
    camera: boolean;
    analyze: boolean;
    processing?: boolean;
    reading?: boolean;
    readingProgress?: number;
    icon?: string;
    frame?: string;
    label?: string;
}>();

const emit = defineEmits<{
    submit: [file: File];
}>();

const preview = ref<string | null>(null);
const selected = ref<File | null>(null);
const live = ref(false);
const reviewing = ref(false);
const video = ref<HTMLVideoElement | null>(null);
const guide = ref<HTMLElement | null>(null);
const stream = ref<MediaStream | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const cameraInput = ref<HTMLInputElement | null>(null);
const error = ref('');

const frameRatio = computed(() => FRAME_RATIOS[props.frame ?? 'id-card'] ?? FRAME_RATIOS['id-card']);
const isPortrait = computed(() => frameRatio.value < 1);
const modalOpen = computed(() => live.value || reviewing.value);

const guideBoxStyle = computed(() => ({
    aspectRatio: String(frameRatio.value),
    width: isPortrait.value ? 'min(72vw, 18rem)' : 'min(88vw, 28rem)',
}));

const stopCamera = () => {
    stream.value?.getTracks().forEach((track) => track.stop());
    stream.value = null;
    live.value = false;
};

const useFile = (file: File | null) => {
    selected.value = file;
    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }
    preview.value = file ? URL.createObjectURL(file) : null;
};

const canvasToFile = (canvas: HTMLCanvasElement, name: string): Promise<File | null> =>
    new Promise((resolve) => {
        canvas.toBlob((blob) => {
            resolve(blob ? new File([blob], name, { type: 'image/jpeg' }) : null);
        }, 'image/jpeg', 0.92);
    });

const cropImageToFrame = async (file: File): Promise<File> => {
    if (!file.type.startsWith('image/')) {
        return file;
    }

    const bitmap = await createImageBitmap(file);
    const ratio = frameRatio.value;
    const sourceRatio = bitmap.width / bitmap.height;
    let sx = 0;
    let sy = 0;
    let sw = bitmap.width;
    let sh = bitmap.height;

    if (sourceRatio > ratio) {
        sw = bitmap.height * ratio;
        sx = (bitmap.width - sw) / 2;
    } else if (sourceRatio < ratio) {
        sh = bitmap.width / ratio;
        sy = (bitmap.height - sh) / 2;
    }

    const canvas = document.createElement('canvas');
    canvas.width = Math.round(sw);
    canvas.height = Math.round(sh);
    canvas.getContext('2d')?.drawImage(bitmap, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height);
    bitmap.close();

    return (await canvasToFile(canvas, file.name.replace(/\.\w+$/, '.jpg'))) ?? file;
};

const onPick = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    input.value = '';

    if (!file) {
        useFile(null);
        reviewing.value = false;
        return;
    }

    try {
        useFile(await cropImageToFrame(file));
    } catch {
        useFile(file);
    }

    stopCamera();
    reviewing.value = true;
};

const openAttach = () => fileInput.value?.click();

const openNativeCamera = () => cameraInput.value?.click();

const startCamera = async () => {
    error.value = '';
    reviewing.value = false;

    if (!navigator.mediaDevices?.getUserMedia) {
        openNativeCamera();
        return;
    }

    try {
        stream.value = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1280 },
                height: { ideal: 1280 },
            },
            audio: false,
        });
        live.value = true;
        await nextTick();
        if (video.value) {
            video.value.srcObject = stream.value;
            await video.value.play();
        }
    } catch {
        error.value = 'No pudimos abrir la cámara. Puedes adjuntar una imagen.';
        openNativeCamera();
    }
};

const mapGuideToVideo = (videoEl: HTMLVideoElement, guideEl: HTMLElement) => {
    const videoRect = videoEl.getBoundingClientRect();
    const guideRect = guideEl.getBoundingClientRect();
    const vw = videoEl.videoWidth || videoRect.width;
    const vh = videoEl.videoHeight || videoRect.height;
    const scale = Math.max(videoRect.width / vw, videoRect.height / vh);
    const shownW = vw * scale;
    const shownH = vh * scale;
    const cropX = (shownW - videoRect.width) / 2;
    const cropY = (shownH - videoRect.height) / 2;
    const sx = Math.max(0, ((guideRect.left - videoRect.left) + cropX) / scale);
    const sy = Math.max(0, ((guideRect.top - videoRect.top) + cropY) / scale);
    const sw = Math.min(vw - sx, guideRect.width / scale);
    const sh = Math.min(vh - sy, guideRect.height / scale);

    return { sx, sy, sw, sh };
};

const snap = async () => {
    if (!video.value || !guide.value) {
        return;
    }

    const { sx, sy, sw, sh } = mapGuideToVideo(video.value, guide.value);

    if (sw < 8 || sh < 8) {
        return;
    }

    const canvas = document.createElement('canvas');
    canvas.width = Math.round(sw);
    canvas.height = Math.round(sh);
    canvas.getContext('2d')?.drawImage(video.value, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height);

    const file = await canvasToFile(canvas, 'captura.jpg');
    if (!file) {
        return;
    }

    useFile(file);
    stopCamera();
    reviewing.value = true;
};

const confirmPhoto = () => {
    reviewing.value = false;
};

const retake = () => {
    useFile(null);
    reviewing.value = false;
    if (props.camera) {
        startCamera();
        return;
    }
    openAttach();
};

const closeModal = () => {
    stopCamera();
    if (reviewing.value) {
        useFile(null);
        reviewing.value = false;
    }
};

const submit = () => {
    if (selected.value) {
        emit('submit', selected.value);
    }
};

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && modalOpen.value) {
        event.preventDefault();
        closeModal();
    }
};

onMounted(() => window.addEventListener('keydown', onKeydown));

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    stopCamera();
    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }
});
</script>

<template>
    <div class="space-y-4">
        <input ref="fileInput" type="file" accept="image/*,.pdf" class="hidden" @change="onPick" />
        <input ref="cameraInput" type="file" accept="image/*" capture="environment" class="hidden" @change="onPick" />

        <div
            class="mx-auto flex items-center justify-center"
            :class="isPortrait ? 'max-w-[16rem]' : 'max-w-lg'"
        >
            <div
                class="relative w-full overflow-hidden rounded-xl border-2 border-dashed border-ember-400/70 bg-ink-900/80"
                :style="{ aspectRatio: String(frameRatio) }"
            >
                <img
                    v-if="preview && !modalOpen"
                    :src="preview"
                    alt="Vista previa del documento"
                    class="absolute inset-0 h-full w-full object-cover"
                />
                <div v-else class="absolute inset-0 flex flex-col items-center justify-center gap-2 p-4 text-center">
                    <DocumentKindIcon :kind="icon ?? 'cedula'" class="h-[58%] w-[70%] max-h-28" />
                    <p class="text-xs text-cream/50">Solo el documento, sin el fondo</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <button type="button" class="btn-primary" @click="startCamera">Tomar foto</button>
            <button type="button" class="btn-ghost" @click="openAttach">Adjuntar archivo</button>
        </div>

        <p v-if="error" class="text-sm text-red-300">{{ error }}</p>

        <p v-if="reading" class="rounded-xl border border-ember-400/30 bg-ember-500/10 px-3 py-2 text-sm text-ember-100">
            Leyendo el texto de la foto… {{ readingProgress ?? 0 }}%
        </p>

        <button
            type="button"
            class="btn-primary w-full"
            :disabled="!selected || processing || modalOpen || reading"
            @click="submit"
        >
            {{ reading
                ? `Leyendo el documento… ${readingProgress ?? 0}%`
                : analyze ? 'Analizar y continuar' : 'Adjuntar y continuar' }}
        </button>

        <Teleport to="body">
            <div
                v-if="modalOpen"
                class="fixed inset-0 z-[100] flex h-[100dvh] w-screen flex-col overflow-hidden bg-black"
                role="dialog"
                aria-modal="true"
            >
                <header class="relative z-20 flex shrink-0 items-center justify-between gap-3 bg-black/70 px-4 py-3 pt-[max(0.75rem,env(safe-area-inset-top))]">
                    <div>
                        <p class="text-xs uppercase tracking-widest text-ember-400">Captura</p>
                        <p class="text-xl font-semibold text-white">{{ label ?? 'Documento' }}</p>
                    </div>
                    <button type="button" class="btn-ghost" @click="closeModal">Cerrar</button>
                </header>

                <div class="relative min-h-0 flex-1">
                    <video
                        v-show="live"
                        ref="video"
                        class="absolute inset-0 h-full w-full object-cover"
                        autoplay
                        playsinline
                        muted
                    />
                    <div
                        v-if="live"
                        class="pointer-events-none absolute inset-0 grid"
                        style="grid-template-rows: 1fr auto 1fr; grid-template-columns: 1fr auto 1fr;"
                    >
                        <div class="col-span-3 bg-black/55" />
                        <div class="bg-black/55" />
                        <div
                            ref="guide"
                            class="relative rounded-xl border-[3px] border-ember-400"
                            :style="guideBoxStyle"
                        >
                            <span class="absolute -left-0.5 -top-0.5 h-7 w-7 rounded-tl-lg border-l-4 border-t-4 border-white" />
                            <span class="absolute -right-0.5 -top-0.5 h-7 w-7 rounded-tr-lg border-r-4 border-t-4 border-white" />
                            <span class="absolute -bottom-0.5 -left-0.5 h-7 w-7 rounded-bl-lg border-b-4 border-l-4 border-white" />
                            <span class="absolute -bottom-0.5 -right-0.5 h-7 w-7 rounded-br-lg border-b-4 border-r-4 border-white" />
                        </div>
                        <div class="bg-black/55" />
                        <div class="col-span-3 bg-black/55" />
                    </div>
                    <img
                        v-if="reviewing && preview"
                        :src="preview"
                        alt="Foto capturada"
                        class="absolute inset-0 h-full w-full object-contain p-4"
                    />
                </div>

                <div class="relative z-20 shrink-0 bg-black/80 px-4 pt-3 pb-[max(1rem,env(safe-area-inset-bottom))]">
                    <p class="mb-3 text-center text-sm text-white/80">
                        {{ reviewing ? 'Revisa que se vea completo y nítido.' : 'Encaja el documento en el recuadro naranja. Solo se guarda esa área.' }}
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <template v-if="live">
                            <button type="button" class="btn-primary py-3 text-base" @click="snap">Tomar foto</button>
                            <button type="button" class="btn-ghost py-3 text-base" @click="closeModal">Cancelar</button>
                        </template>
                        <template v-else>
                            <button type="button" class="btn-primary py-3 text-base" @click="confirmPhoto">Usar esta foto</button>
                            <button type="button" class="btn-ghost py-3 text-base" @click="retake">Repetir</button>
                        </template>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
