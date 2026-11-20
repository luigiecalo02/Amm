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
    readingLabel?: string;
    icon?: string;
    frame?: string;
    label?: string;
}>();

const emit = defineEmits<{
    submit: [file: File];
    skip: [];
}>();

const preview = ref<string | null>(null);
const selected = ref<File | null>(null);
const live = ref(false);
const reviewing = ref(false);
const placing = ref(false);
const confirming = ref(false);
const video = ref<HTMLVideoElement | null>(null);
const guide = ref<HTMLElement | null>(null);
const stage = ref<HTMLElement | null>(null);
const placedImg = ref<HTMLImageElement | null>(null);
const stream = ref<MediaStream | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const cameraInput = ref<HTMLInputElement | null>(null);
const error = ref('');
const panX = ref(0);
const panY = ref(0);
const scale = ref(1);
const rotation = ref(0);
const naturalW = ref(0);
const naturalH = ref(0);
const pointers = new Map<number, { x: number; y: number }>();
let pinchStartDistance = 0;
let pinchStartScale = 1;

const frameRatio = computed(() => FRAME_RATIOS[props.frame ?? 'id-card'] ?? FRAME_RATIOS['id-card']);
const isPortrait = computed(() => frameRatio.value < 1);
const modalOpen = computed(() => live.value || reviewing.value);

const guideBoxStyle = computed(() => ({
    aspectRatio: String(frameRatio.value),
    width: isPortrait.value ? 'min(72vw, 18rem)' : 'min(88vw, 28rem)',
}));

const placedStyle = computed(() => ({
    width: `${naturalW.value}px`,
    height: `${naturalH.value}px`,
    transform: `translate(-50%, -50%) translate(${panX.value}px, ${panY.value}px) rotate(${rotation.value}deg) scale(${scale.value})`,
}));

const minScale = computed(() => coverScale() * 0.6);
const maxScale = computed(() => coverScale() * 6);

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

const resetPlacement = () => {
    panX.value = 0;
    panY.value = 0;
    scale.value = 1;
    rotation.value = 0;
    naturalW.value = 0;
    naturalH.value = 0;
    pointers.clear();
    pinchStartDistance = 0;
};

const canvasToFile = (canvas: HTMLCanvasElement, name: string): Promise<File | null> =>
    new Promise((resolve) => {
        canvas.toBlob((blob) => {
            resolve(blob ? new File([blob], name, { type: 'image/jpeg' }) : null);
        }, 'image/jpeg', 0.92);
    });

const coverScale = () => {
    const frame = guide.value;
    const width = naturalW.value;
    const height = naturalH.value;

    if (!frame || width < 1 || height < 1) {
        return 1;
    }

    const radians = (rotation.value * Math.PI) / 180;
    const rotatedW = Math.abs(width * Math.cos(radians)) + Math.abs(height * Math.sin(radians));
    const rotatedH = Math.abs(width * Math.sin(radians)) + Math.abs(height * Math.cos(radians));

    return Math.max(frame.clientWidth / rotatedW, frame.clientHeight / rotatedH);
};

const fitToFrame = async () => {
    await nextTick();

    if (!placedImg.value) {
        return;
    }

    naturalW.value = placedImg.value.naturalWidth;
    naturalH.value = placedImg.value.naturalHeight;
    panX.value = 0;
    panY.value = 0;
    scale.value = coverScale() * 1.04;
};

const clampScale = (value: number) => Math.min(maxScale.value, Math.max(minScale.value, value));

const rotateBy = async (degrees: number) => {
    rotation.value = (rotation.value + degrees) % 360;

    if (rotation.value < 0) {
        rotation.value += 360;
    }

    panX.value = 0;
    panY.value = 0;
    await nextTick();
    scale.value = coverScale() * 1.04;
};

const zoomBy = (factor: number) => {
    scale.value = clampScale(scale.value * factor);
};

const pointerDistance = () => {
    const points = [...pointers.values()];

    if (points.length < 2) {
        return 0;
    }

    return Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y);
};

const onPointerDown = (event: PointerEvent) => {
    if (!placing.value) {
        return;
    }

    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

    if (pointers.size === 2) {
        pinchStartDistance = pointerDistance();
        pinchStartScale = scale.value;
    }
};

const onPointerMove = (event: PointerEvent) => {
    if (!placing.value || !pointers.has(event.pointerId)) {
        return;
    }

    const previous = pointers.get(event.pointerId);
    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

    if (!previous) {
        return;
    }

    if (pointers.size === 1) {
        panX.value += event.clientX - previous.x;
        panY.value += event.clientY - previous.y;
        return;
    }

    if (pointers.size === 2 && pinchStartDistance > 0) {
        scale.value = clampScale(pinchStartScale * (pointerDistance() / pinchStartDistance));
    }
};

const onPointerUp = (event: PointerEvent) => {
    pointers.delete(event.pointerId);

    if (pointers.size < 2) {
        pinchStartDistance = 0;
    }
};

const onWheel = (event: WheelEvent) => {
    if (!placing.value) {
        return;
    }

    event.preventDefault();
    zoomBy(event.deltaY > 0 ? 0.92 : 1.08);
};

const cropPlacement = async (): Promise<File | null> => {
    const image = placedImg.value;
    const frame = guide.value;
    const viewport = stage.value;

    if (!image || !frame || !viewport || naturalW.value < 1) {
        return selected.value;
    }

    const stageRect = viewport.getBoundingClientRect();
    const guideRect = frame.getBoundingClientRect();
    const pixelRatio = 2;
    const scene = document.createElement('canvas');
    scene.width = Math.max(1, Math.round(stageRect.width * pixelRatio));
    scene.height = Math.max(1, Math.round(stageRect.height * pixelRatio));
    const context = scene.getContext('2d');

    if (!context) {
        return selected.value;
    }

    context.scale(pixelRatio, pixelRatio);
    context.translate(stageRect.width / 2 + panX.value, stageRect.height / 2 + panY.value);
    context.rotate((rotation.value * Math.PI) / 180);
    context.scale(scale.value, scale.value);
    context.drawImage(image, -naturalW.value / 2, -naturalH.value / 2, naturalW.value, naturalH.value);

    const cropX = (guideRect.left - stageRect.left) * pixelRatio;
    const cropY = (guideRect.top - stageRect.top) * pixelRatio;
    const cropW = Math.max(8, guideRect.width * pixelRatio);
    const cropH = Math.max(8, guideRect.height * pixelRatio);
    const output = document.createElement('canvas');
    output.width = Math.round(cropW);
    output.height = Math.round(cropH);
    output.getContext('2d')?.drawImage(scene, cropX, cropY, cropW, cropH, 0, 0, output.width, output.height);

    return canvasToFile(output, (selected.value?.name ?? 'documento.jpg').replace(/\.\w+$/, '.jpg'));
};

const onPick = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    input.value = '';

    if (!file) {
        useFile(null);
        reviewing.value = false;
        placing.value = false;
        return;
    }

    stopCamera();
    resetPlacement();
    useFile(file);
    placing.value = file.type.startsWith('image/');
    reviewing.value = true;
    await nextTick();

    if (placing.value) {
        await fitToFrame();
    }
};

const openAttach = () => fileInput.value?.click();

const openNativeCamera = () => cameraInput.value?.click();

const startCamera = async () => {
    error.value = '';
    reviewing.value = false;
    placing.value = false;

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
    const videoScale = Math.max(videoRect.width / vw, videoRect.height / vh);
    const shownW = vw * videoScale;
    const shownH = vh * videoScale;
    const cropX = (shownW - videoRect.width) / 2;
    const cropY = (shownH - videoRect.height) / 2;
    const sx = Math.max(0, ((guideRect.left - videoRect.left) + cropX) / videoScale);
    const sy = Math.max(0, ((guideRect.top - videoRect.top) + cropY) / videoScale);
    const sw = Math.min(vw - sx, guideRect.width / videoScale);
    const sh = Math.min(vh - sy, guideRect.height / videoScale);

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
    placing.value = false;
    reviewing.value = true;
};

const confirmPhoto = async () => {
    if (placing.value) {
        confirming.value = true;

        try {
            const cropped = await cropPlacement();

            if (cropped) {
                useFile(cropped);
            }
        } finally {
            confirming.value = false;
            placing.value = false;
        }
    }

    reviewing.value = false;
};

const retake = () => {
    useFile(null);
    reviewing.value = false;
    placing.value = false;
    resetPlacement();
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
        placing.value = false;
        resetPlacement();
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
            <button type="button" class="btn-primary" :disabled="reading || processing" @click="startCamera">Tomar foto</button>
            <button type="button" class="btn-ghost" :disabled="reading || processing" @click="openAttach">Adjuntar archivo</button>
        </div>

        <p v-if="error" class="text-sm text-red-300">{{ error }}</p>

        <div v-if="reading" class="rounded-xl border border-ember-400/30 bg-ember-500/10 px-3 py-2 text-sm text-ember-100">
            <p>{{ readingLabel || 'Leyendo el documento…' }} {{ readingProgress ?? 0 }}%</p>
            <button type="button" class="mt-1 text-xs text-cream/70 underline decoration-white/30" @click="emit('skip')">
                Subir sin esperar la lectura
            </button>
        </div>

        <button
            type="button"
            class="btn-primary w-full"
            :disabled="!selected || processing || modalOpen || reading"
            @click="submit"
        >
            {{ reading ? 'Leyendo…' : processing ? 'Guardando…' : analyze ? 'Analizar y continuar' : 'Adjuntar y continuar' }}
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

                <div
                    ref="stage"
                    class="relative min-h-0 flex-1 touch-none overflow-hidden"
                    @pointerdown="onPointerDown"
                    @pointermove="onPointerMove"
                    @pointerup="onPointerUp"
                    @pointercancel="onPointerUp"
                    @wheel="onWheel"
                >
                    <video
                        v-show="live"
                        ref="video"
                        class="absolute inset-0 h-full w-full object-cover"
                        autoplay
                        playsinline
                        muted
                    />
                    <img
                        v-if="reviewing && preview && placing"
                        ref="placedImg"
                        :src="preview"
                        alt="Foto capturada"
                        class="absolute left-1/2 top-1/2 max-w-none origin-center select-none will-change-transform"
                        draggable="false"
                        :style="placedStyle"
                        @load="fitToFrame"
                    />
                    <img
                        v-else-if="reviewing && preview"
                        :src="preview"
                        alt="Foto capturada"
                        class="absolute inset-0 h-full w-full object-contain p-4"
                    />
                    <div
                        v-if="live || placing"
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
                </div>

                <div class="relative z-20 shrink-0 bg-black/80 px-4 pt-3 pb-[max(1rem,env(safe-area-inset-bottom))]">
                    <p class="mb-3 text-center text-sm text-white/80">
                        {{ placing
                            ? 'Arrastra la foto, gírala y acércala hasta encajarla en el recuadro naranja.'
                            : reviewing ? 'Revisa que se vea completo y nítido.' : 'Encaja el documento en el recuadro naranja. Solo se guarda esa área.' }}
                    </p>
                    <div v-if="placing" class="mb-3 grid grid-cols-4 gap-2">
                        <button type="button" class="btn-ghost py-2 text-sm" @click="rotateBy(-90)">Girar izq.</button>
                        <button type="button" class="btn-ghost py-2 text-sm" @click="rotateBy(90)">Girar der.</button>
                        <button type="button" class="btn-ghost py-2 text-sm" @click="zoomBy(0.86)">Alejar</button>
                        <button type="button" class="btn-ghost py-2 text-sm" @click="zoomBy(1.16)">Acercar</button>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <template v-if="live">
                            <button type="button" class="btn-primary py-3 text-base" @click="snap">Tomar foto</button>
                            <button type="button" class="btn-ghost py-3 text-base" @click="closeModal">Cancelar</button>
                        </template>
                        <template v-else>
                            <button type="button" class="btn-primary py-3 text-base" :disabled="confirming" @click="confirmPhoto">
                                {{ confirming ? 'Recortando…' : 'Usar esta foto' }}
                            </button>
                            <button type="button" class="btn-ghost py-3 text-base" :disabled="confirming" @click="retake">Repetir</button>
                        </template>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
