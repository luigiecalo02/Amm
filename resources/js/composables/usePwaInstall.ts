import { computed, onMounted, onUnmounted, ref } from 'vue';

type InstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

declare global {
    interface Window {
        __ammInstallPrompt?: InstallPromptEvent;
    }
}

const isStandalone = () =>
    window.matchMedia('(display-mode: standalone)').matches
    || ('standalone' in navigator && Boolean((navigator as Navigator & { standalone?: boolean }).standalone));

export function usePwaInstall() {
    const promptEvent = ref<InstallPromptEvent | null>(window.__ammInstallPrompt ?? null);
    const installed = ref(isStandalone());
    const hint = ref('');
    const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);

    const canInstall = computed(() => !installed.value);

    const capturePrompt = (event: Event) => {
        event.preventDefault();
        promptEvent.value = event as InstallPromptEvent;
        window.__ammInstallPrompt = promptEvent.value;
        hint.value = '';
    };

    const markInstalled = () => {
        installed.value = true;
        promptEvent.value = null;
        window.__ammInstallPrompt = undefined;
        hint.value = '';
    };

    const install = async () => {
        if (promptEvent.value) {
            await promptEvent.value.prompt();
            const choice = await promptEvent.value.userChoice;

            if (choice.outcome === 'accepted') {
                markInstalled();
                return;
            }

            promptEvent.value = null;
            window.__ammInstallPrompt = undefined;
            return;
        }

        if (isIos) {
            hint.value = 'En Safari, toca Compartir y luego Añadir a pantalla de inicio.';
            return;
        }

        hint.value = 'Abre el menú del navegador y elige Instalar aplicación o Añadir a la pantalla de inicio.';
    };

    onMounted(() => {
        window.addEventListener('beforeinstallprompt', capturePrompt);
        window.addEventListener('appinstalled', markInstalled);
    });

    onUnmounted(() => {
        window.removeEventListener('beforeinstallprompt', capturePrompt);
        window.removeEventListener('appinstalled', markInstalled);
    });

    return {
        canInstall,
        installed,
        isIos,
        hint,
        hasPrompt: computed(() => promptEvent.value !== null),
        install,
    };
}
