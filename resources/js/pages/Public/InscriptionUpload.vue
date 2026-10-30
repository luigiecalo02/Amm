<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import DocumentKindIcon from '@/components/DocumentKindIcon.vue';
import DocumentStepCapture from '@/components/DocumentStepCapture.vue';
import InscriptionExtractionSummary from '@/components/InscriptionExtractionSummary.vue';
import InscriptionWizardNav from '@/components/InscriptionWizardNav.vue';
import type { ExtractionReview } from '@/components/InscriptionExtractionSummary.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';
import { extractTextFromImage } from '@/lib/imageOcr';

type Step = {
    key: string;
    label: string;
    hint: string;
    type: string | null;
    analyze: boolean;
    camera: boolean;
    icon: string;
    frame: string;
    group: string;
    group_label: string;
    side: 'front' | 'back' | null;
    validate: boolean;
    done: boolean;
    preview_url?: string | null;
};

type StepGroup = {
    key: string;
    label: string;
    icon: string;
    front?: Step;
    back?: Step;
    single?: Step;
};

const props = defineProps<{
    club: { name: string; city?: string | null; slug: string };
    token: string;
    steps: Step[];
    current: Step | null;
    role: 'driver' | 'passenger';
    documents_complete: boolean;
    progress: { done: number; total: number };
    review: ExtractionReview | null;
}>();

const page = usePage<PageProps>();
const busy = ref(false);
const reading = ref(false);
const readingProgress = ref(0);
const selectedKey = ref<string | null>(null);
const reviewOpen = ref(false);
const choiceGroup = ref<StepGroup | null>(null);
const choiceStep = ref<Step | null>(null);
const choiceMode = ref<'ask' | 'pick-side'>('ask');
const infoGroup = ref<StepGroup | null>(null);
const storageKey = computed(() => `amm-inscription-step:${props.token}`);
const selected = computed(() => props.steps.find((step) => step.key === selectedKey.value) ?? null);
const captureOpen = computed(() => selected.value !== null);
const choiceOpen = computed(() => choiceGroup.value !== null);
const infoOpen = computed(() => infoGroup.value !== null);
const anyModalOpen = computed(() => captureOpen.value || reviewOpen.value || choiceOpen.value || infoOpen.value);
const infoReview = computed(() => (
    props.review && infoGroup.value && props.review.group === infoGroup.value.key
        ? props.review
        : null
));

const closeCapture = () => {
    selectedKey.value = null;
};

const lockBody = (locked: boolean) => {
    document.body.style.overflow = locked ? 'hidden' : '';
};

const closeReview = () => {
    reviewOpen.value = false;
};

const closeChoice = () => {
    choiceGroup.value = null;
    choiceStep.value = null;
    choiceMode.value = 'ask';
};

const closeInfo = () => {
    infoGroup.value = null;
};

const groupSides = (group: StepGroup): Step[] => (
    [group.front, group.back, group.single].filter(Boolean) as Step[]
);

const groupComplete = (group: StepGroup): boolean => {
    const sides = groupSides(group);
    return sides.length > 0 && sides.every((step) => step.done);
};

const openChoice = (group: StepGroup, step?: Step) => {
    choiceGroup.value = group;
    choiceStep.value = step ?? null;
    choiceMode.value = 'ask';
};

const viewLoadedInfo = () => {
    infoGroup.value = choiceGroup.value;
    closeChoice();
};

const changeFromInfo = () => {
    const group = infoGroup.value;
    closeInfo();
    if (group) {
        openChoice(group);
    }
};

const startChange = () => {
    if (choiceStep.value) {
        const step = choiceStep.value;
        closeChoice();
        selectedKey.value = step.key;
        return;
    }

    const group = choiceGroup.value;
    if (! group) {
        return;
    }

    if (group.single || (! group.front || ! group.back)) {
        const step = group.single ?? group.front ?? group.back;
        closeChoice();
        selectedKey.value = step?.key ?? null;
        return;
    }

    choiceMode.value = 'pick-side';
};

const onCaptureKeydown = (event: KeyboardEvent) => {
    if (event.key !== 'Escape' || event.defaultPrevented) {
        return;
    }

    if (captureOpen.value) {
        closeCapture();
        return;
    }

    if (infoOpen.value) {
        closeInfo();
        return;
    }

    if (choiceOpen.value) {
        closeChoice();
        return;
    }

    if (reviewOpen.value) {
        closeReview();
    }
};

onMounted(() => {
    const saved = sessionStorage.getItem(storageKey.value);
    const savedStep = props.steps.find((step) => step.key === saved);

    if (savedStep && !savedStep.done) {
        selectedKey.value = savedStep.key;
    }

    window.addEventListener('keydown', onCaptureKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onCaptureKeydown);
    lockBody(false);
});

watch(selectedKey, (key) => {
    if (key) {
        sessionStorage.setItem(storageKey.value, key);
        return;
    }

    sessionStorage.removeItem(storageKey.value);
});

watch(anyModalOpen, (open) => lockBody(open));

watch(
    () => [page.props.flash?.analyzed, props.review] as const,
    ([analyzed, review]) => {
        if (! review) {
            reviewOpen.value = false;
            return;
        }

        reviewOpen.value = Boolean(analyzed || review.both_sides);
    },
    { immediate: true },
);

watch(
    () => props.steps,
    (steps) => {
        const current = steps.find((step) => step.key === selectedKey.value);
        if (current?.done) {
            selectedKey.value = null;
        }
    },
);

const percent = computed(() => Math.round((props.progress.done / props.progress.total) * 100));
const fileError = computed(() => (page.props.errors as Record<string, string> | undefined)?.file ?? '');

const groups = computed<StepGroup[]>(() => {
    const result: StepGroup[] = [];
    const index = new Map<string, StepGroup>();

    for (const step of props.steps) {
        let group = index.get(step.group);
        if (!group) {
            group = { key: step.group, label: step.group_label, icon: step.icon };
            index.set(step.group, group);
            result.push(group);
        }

        if (step.side === 'front') {
            group.front = step;
        } else if (step.side === 'back') {
            group.back = step;
        } else {
            group.single = step;
        }
    }

    return result;
});

const parentPreview = computed(() => {
    if (selected.value?.side !== 'back') {
        return null;
    }

    return props.steps.find((step) => step.group === selected.value?.group && step.side === 'front') ?? null;
});

const groupTone = (group: StepGroup) => {
    const sides = [group.front, group.back, group.single].filter(Boolean) as Step[];
    if (sides.some((step) => step.key === selected.value?.key)) {
        return 'border-ember-400/40 bg-ember-500/10';
    }
    if (sides.length > 0 && sides.every((step) => step.done)) {
        return 'border-emerald-400/20 bg-emerald-500/10';
    }
    return 'border-white/10 bg-white/5 hover:border-white/25';
};

const sideTone = (step?: Step) => {
    if (!step) {
        return 'text-cream/40';
    }
    if (step.done) {
        return 'text-emerald-200';
    }
    if (step.key === selected.value?.key) {
        return 'text-ember-300';
    }
    return 'text-cream/45';
};

const choose = (step?: Step) => {
    if (!step) {
        return;
    }

    if (step.done) {
        const group = groups.value.find((item) => (
            item.front?.key === step.key || item.back?.key === step.key || item.single?.key === step.key
        ));

        if (group) {
            openChoice(group, step);
            return;
        }
    }

    selectedKey.value = step.key;
};

const chooseGroup = (group: StepGroup) => {
    if (groupComplete(group)) {
        openChoice(group);
        return;
    }

    const next = [group.front, group.back, group.single].find((step) => step && !step.done)
        ?? group.front
        ?? group.single
        ?? group.back;
    choose(next);
};

const submit = async (file: File) => {
    if (!selected.value) {
        return;
    }

    busy.value = true;
    let ocrText = '';

    if (selected.value.analyze && file.type.startsWith('image/')) {
        reading.value = true;
        readingProgress.value = 0;

        try {
            ocrText = await extractTextFromImage(file, (percent) => {
                readingProgress.value = percent;
            });
        } catch {
            ocrText = '';
        } finally {
            reading.value = false;
        }
    }

    router.post(`/i/${props.token}/documentos`, {
        step: selected.value.key,
        file,
        ocr_text: ocrText,
    }, {
        forceFormData: true,
        onFinish: () => {
            busy.value = false;
        },
    });
};
</script>

<template>
    <PublicLayout :club="club">
        <InscriptionWizardNav :token="token" current="documents" basics-done />
        <div v-if="page.props.flash?.success" class="mb-4 rounded-xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
            {{ page.props.flash.success }}
        </div>
        <p class="mb-4 text-sm text-cream/60">
            {{ role === 'passenger'
                ? 'Puedes subir la cédula ahora o pasar al formulario. Si algo salió mal, toca frente o reverso para volver a tomarlo.'
                : 'Los documentos son opcionales. Si la lectura falla, toca el lado listo y súbelo de nuevo.' }}
        </p>

        <div class="mb-5">
            <div class="mb-2 flex justify-between text-xs text-cream/50">
                <span>Paso {{ progress.done + (current ? 1 : 0) }} de {{ progress.total }}</span>
                <span>{{ percent }}%</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-white/10">
                <div class="h-full bg-ember-400 transition-all" :style="{ width: `${percent}%` }" />
            </div>
        </div>

        <ol class="mb-5 grid gap-3 md:grid-cols-2">
            <li
                v-for="group in groups"
                :key="group.key"
                class="rounded-2xl border p-3 transition"
                :class="groupTone(group)"
            >
                <button type="button" class="flex w-full items-center gap-2 text-left" @click="chooseGroup(group)">
                    <span class="flex h-9 w-12 shrink-0 items-center justify-center overflow-hidden rounded-md bg-black/30 p-0.5">
                        <DocumentKindIcon :kind="group.icon" />
                    </span>
                    <p class="font-medium">{{ group.label }}</p>
                </button>

                <div v-if="group.front || group.back" class="mt-3 space-y-2">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 rounded-lg px-1 py-1 text-left hover:bg-white/5"
                        :class="sideTone(group.front)"
                        @click="choose(group.front)"
                    >
                        <img
                            v-if="group.front?.preview_url"
                            :src="group.front.preview_url"
                            alt="Frente"
                            class="h-10 w-16 rounded-md object-cover"
                        />
                        <span v-else class="flex h-10 w-16 items-center justify-center rounded-md bg-black/30 text-[10px]">Frente</span>
                        <span class="text-xs">{{ group.front?.done ? 'Frente listo · cambiar' : 'Subir frente' }}</span>
                    </button>
                    <button
                        type="button"
                        class="ml-6 flex w-[calc(100%-1.5rem)] items-center gap-2 rounded-lg border-l border-white/15 px-1 py-1 pl-3 text-left hover:bg-white/5"
                        :class="sideTone(group.back)"
                        @click="choose(group.back)"
                    >
                        <img
                            v-if="group.back?.preview_url"
                            :src="group.back.preview_url"
                            alt="Reverso"
                            class="h-8 w-12 rounded-md object-cover"
                        />
                        <span v-else class="flex h-8 w-12 items-center justify-center rounded-md bg-black/20 text-[10px]">Rev.</span>
                        <span class="text-xs">{{ group.back?.done ? 'Reverso listo · cambiar' : 'Subir reverso' }}</span>
                    </button>
                </div>
                <button
                    v-else
                    type="button"
                    class="mt-2 text-left text-xs"
                    :class="sideTone(group.single)"
                    @click="choose(group.single)"
                >
                    {{ group.single?.done ? 'Listo · cambiar' : 'Seleccionar para subir' }}
                </button>
            </li>
        </ol>

        <button
            v-if="review && !reviewOpen"
            type="button"
            class="panel mb-4 flex w-full items-center justify-between gap-3 p-4 text-left"
            @click="reviewOpen = true"
        >
            <span>
                <span class="block text-xs uppercase tracking-widest text-ember-400">Resumen · {{ review.group_label }}</span>
                <span class="mt-1 block text-sm text-cream/70">{{ review.headline }}</span>
            </span>
            <span class="btn-ghost shrink-0">Ver</span>
        </button>

        <Teleport to="body">
            <div
                v-if="choiceGroup"
                class="fixed inset-0 z-[55] flex items-end justify-center bg-black/70 p-3 sm:items-center sm:p-6"
                role="dialog"
                aria-modal="true"
                aria-label="Documento cargado"
                @click.self="closeChoice"
            >
                <section class="panel w-full max-w-md space-y-4 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-widest text-ember-400">{{ choiceGroup.label }}</p>
                            <p class="font-display text-2xl">Ya está cargado</p>
                            <p class="mt-1 text-sm text-cream/60">
                                ¿Quieres ver lo que hay o cambiar las fotos?
                            </p>
                        </div>
                        <button type="button" class="btn-ghost shrink-0" @click="closeChoice">Cerrar</button>
                    </div>

                    <div v-if="choiceMode === 'ask'" class="grid gap-2">
                        <button type="button" class="btn-primary" @click="viewLoadedInfo">Ver información</button>
                        <button type="button" class="btn-ghost" @click="startChange">
                            {{ choiceStep ? 'Cambiar este lado' : 'Cambiar documentos' }}
                        </button>
                    </div>
                    <div v-else class="grid gap-2">
                        <button
                            v-if="choiceGroup.front"
                            type="button"
                            class="btn-primary"
                            @click="selectedKey = choiceGroup.front.key; closeChoice()"
                        >
                            Cambiar frente
                        </button>
                        <button
                            v-if="choiceGroup.back"
                            type="button"
                            class="btn-ghost"
                            @click="selectedKey = choiceGroup.back.key; closeChoice()"
                        >
                            Cambiar reverso
                        </button>
                    </div>
                </section>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="infoGroup"
                class="fixed inset-0 z-[60] flex items-end justify-center bg-black/70 p-3 sm:items-center sm:p-6"
                role="dialog"
                aria-modal="true"
                aria-label="Información del documento"
                @click.self="closeInfo"
            >
                <section class="panel max-h-[92dvh] w-full max-w-3xl overflow-y-auto p-5">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-widest text-ember-400">{{ infoGroup.label }}</p>
                            <p class="font-display text-2xl">Información cargada</p>
                        </div>
                        <button type="button" class="btn-ghost shrink-0" @click="closeInfo">Cerrar</button>
                    </div>

                    <div class="mb-4 grid gap-3 sm:grid-cols-2">
                        <figure v-if="infoGroup.front?.preview_url" class="rounded-xl border border-white/10 bg-black/20 p-2">
                            <figcaption class="mb-2 text-xs uppercase tracking-widest text-cream/50">Frente</figcaption>
                            <img :src="infoGroup.front.preview_url" alt="Frente" class="mx-auto max-h-52 w-full rounded-lg object-contain" />
                        </figure>
                        <figure v-if="infoGroup.back?.preview_url" class="rounded-xl border border-white/10 bg-black/20 p-2">
                            <figcaption class="mb-2 text-xs uppercase tracking-widest text-cream/50">Reverso</figcaption>
                            <img :src="infoGroup.back.preview_url" alt="Reverso" class="mx-auto max-h-52 w-full rounded-lg object-contain" />
                        </figure>
                        <figure v-if="infoGroup.single?.preview_url" class="rounded-xl border border-white/10 bg-black/20 p-2 sm:col-span-2">
                            <figcaption class="mb-2 text-xs uppercase tracking-widest text-cream/50">Archivo</figcaption>
                            <img :src="infoGroup.single.preview_url" alt="Documento" class="mx-auto max-h-52 w-full rounded-lg object-contain" />
                        </figure>
                    </div>

                    <InscriptionExtractionSummary v-if="infoReview" :review="infoReview" />
                    <p v-else class="rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-cream/60">
                        No hay texto leído en este documento. Puedes cambiar las fotos si quieres volver a intentarlo.
                    </p>

                    <div class="mt-5 grid gap-2 sm:grid-cols-2">
                        <button type="button" class="btn-ghost" @click="changeFromInfo">Cambiar documentos</button>
                        <button type="button" class="btn-primary" @click="closeInfo">Listo</button>
                    </div>
                </section>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="reviewOpen && review"
                class="fixed inset-0 z-[60] flex items-end justify-center bg-black/70 p-3 sm:items-center sm:p-6"
                role="dialog"
                aria-modal="true"
                aria-label="Resumen de datos leídos"
                @click.self="closeReview"
            >
                <section class="panel max-h-[92dvh] w-full max-w-3xl overflow-y-auto p-5">
                    <div class="mb-4 flex justify-end">
                        <button type="button" class="btn-ghost" @click="closeReview">Cerrar</button>
                    </div>
                    <InscriptionExtractionSummary :review="review" />
                    <div class="mt-5 grid gap-2 sm:grid-cols-2">
                        <Link :href="`/i/${token}/datos`" class="btn-ghost">Corregir datos iniciales</Link>
                        <button type="button" class="btn-primary" @click="closeReview">Continuar</button>
                    </div>
                </section>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="selected"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 p-3 sm:items-center sm:p-6"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="`capture-title-${selected.key}`"
                @click.self="closeCapture"
            >
                <section class="panel max-h-[92dvh] w-full max-w-2xl overflow-y-auto p-5">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="flex h-14 w-[5.5rem] shrink-0 items-center justify-center overflow-hidden rounded-xl border border-white/10 bg-ink-900/80 p-1">
                                <DocumentKindIcon :kind="selected.icon" />
                            </span>
                            <div>
                                <p :id="`capture-title-${selected.key}`" class="font-display text-2xl">
                                    {{ selected.done ? `Cambiar ${selected.label}` : selected.label }}
                                </p>
                                <p class="mt-1 text-sm text-cream/60">
                                    {{ selected.done
                                        ? 'Toma otra foto o adjunta el archivo de nuevo para volver a leer este lado.'
                                        : selected.hint }}
                                </p>
                            </div>
                        </div>
                        <button type="button" class="btn-ghost shrink-0" @click="closeCapture">Cerrar</button>
                    </div>

                    <div v-if="parentPreview" class="mb-4 rounded-2xl border border-white/10 bg-black/20 p-3">
                        <p class="mb-2 text-xs uppercase tracking-widest text-cream/50">Foto principal</p>
                        <img
                            v-if="parentPreview.preview_url"
                            :src="parentPreview.preview_url"
                            :alt="parentPreview.label"
                            class="mx-auto max-h-40 w-full max-w-md rounded-xl object-contain"
                        />
                        <div class="mt-3 border-l-2 border-ember-400 pl-3">
                            <p class="text-sm font-medium">Reverso anidado</p>
                            <p class="text-xs text-cream/50">Esta cara queda unida a la foto principal.</p>
                        </div>
                    </div>

                    <p v-if="fileError" class="mb-3 rounded-xl border border-red-400/30 bg-red-500/10 px-3 py-2 text-sm text-red-200">
                        {{ fileError }}
                    </p>

                    <DocumentStepCapture
                        :key="selected.key"
                        :camera="selected.camera"
                        :analyze="selected.analyze"
                        :processing="busy"
                        :reading="reading"
                        :reading-progress="readingProgress"
                        :icon="selected.icon"
                        :frame="selected.frame"
                        :label="selected.label"
                        @submit="submit"
                    />
                </section>
            </div>
        </Teleport>

        <section class="panel mt-5 space-y-3 p-5">
            <p class="font-display text-xl">{{ documents_complete ? 'Documentos listos' : 'Paso 3 · Formulario' }}</p>
            <p class="text-sm text-cream/60">
                Los documentos no son obligatorios. Puedes ir al formulario ahora y completar el formato AMM.
            </p>
            <div class="flex flex-col gap-2 md:flex-row">
                <button
                    type="button"
                    class="btn-primary"
                    :disabled="busy"
                    @click="router.post(`/i/${token}/continuar`)"
                >
                    Ir al formulario
                </button>
                <button
                    v-if="role === 'passenger' && documents_complete"
                    type="button"
                    class="btn-ghost"
                    :disabled="busy"
                    @click="router.post(`/i/${token}/enviar`)"
                >
                    Enviar solo con cédula
                </button>
            </div>
        </section>
    </PublicLayout>
</template>
