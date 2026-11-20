import { router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

function keyBytes(value: string): ArrayBuffer {
    const padding = '='.repeat((4 - (value.length % 4)) % 4);
    const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    const buffer = new ArrayBuffer(raw.length);
    const bytes = new Uint8Array(buffer);

    for (let index = 0; index < raw.length; index += 1) {
        bytes[index] = raw.charCodeAt(index);
    }

    return buffer;
}

export function useWebPush(publicKey: string | null | undefined) {
    const supported = computed(() => (
        typeof window !== 'undefined'
        && 'serviceWorker' in navigator
        && 'PushManager' in window
        && 'Notification' in window
        && Boolean(publicKey)
    ));
    const enabled = ref(false);
    const enabling = ref(false);
    const blocked = ref(false);

    onMounted(async () => {
        if (!supported.value) {
            return;
        }

        blocked.value = Notification.permission === 'denied';
        const registration = await navigator.serviceWorker.ready;
        enabled.value = Boolean(await registration.pushManager.getSubscription());
    });

    const enable = async () => {
        if (!supported.value || !publicKey || enabling.value) {
            return;
        }

        enabling.value = true;

        try {
            const permission = await Notification.requestPermission();
            blocked.value = permission === 'denied';

            if (permission !== 'granted') {
                return;
            }

            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: keyBytes(publicKey),
            });
            const json = subscription.toJSON();

            router.post('/push/suscripcion', {
                endpoint: json.endpoint,
                public_key: json.keys?.p256dh,
                auth_token: json.keys?.auth,
                content_encoding: 'aes128gcm',
            }, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    enabled.value = true;
                },
            });
        } finally {
            enabling.value = false;
        }
    };

    const disable = async () => {
        if (!supported.value || enabling.value) {
            return;
        }

        enabling.value = true;

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();

            if (!subscription) {
                enabled.value = false;

                return;
            }

            const endpoint = subscription.endpoint;
            await subscription.unsubscribe();

            router.delete('/push/suscripcion', {
                data: { endpoint },
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    enabled.value = false;
                },
            });
        } finally {
            enabling.value = false;
        }
    };

    return { supported, enabled, enabling, blocked, enable, disable };
}
