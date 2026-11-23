import { onBeforeUnmount, onMounted, watch, type Ref } from 'vue';

type ShrinkToFitOptions = {
    maxFontRem?: number;
    minFontRem?: number;
    maxLines?: number;
    mediaQuery?: string;
};

export function useShrinkToFit(
    target: Ref<HTMLElement | null>,
    source: Ref<string>,
    options: ShrinkToFitOptions = {},
) {
    const maxFontRem = options.maxFontRem ?? 1.85;
    const minFontRem = options.minFontRem ?? 0.8;
    const maxLines = options.maxLines ?? 2;
    const mediaQuery = options.mediaQuery ?? '(max-width: 767px)';

    let observer: ResizeObserver | null = null;
    let media: MediaQueryList | null = null;
    let frame = 0;
    let lastWidth = -1;
    let lastSize = '';

    const rootFontSize = () => parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;

    const overflows = (element: HTMLElement, size: number) => {
        element.style.fontSize = `${size}px`;
        const lineHeight = parseFloat(getComputedStyle(element).lineHeight) || size;
        const lines = Math.round(element.scrollHeight / lineHeight);

        return lines > maxLines || element.scrollWidth > element.clientWidth + 1;
    };

    const fit = () => {
        const element = target.value;

        if (!element || element.clientWidth === 0) {
            return;
        }

        if (!window.matchMedia(mediaQuery).matches) {
            lastWidth = element.clientWidth;
            lastSize = '';
            element.style.fontSize = '';

            return;
        }

        const root = rootFontSize();
        const max = maxFontRem * root;
        const min = minFontRem * root;

        const width = element.clientWidth;
        let size = '';

        if (overflows(element, max)) {
            let low = min;
            let high = max;

            for (let step = 0; step < 12; step += 1) {
                const mid = (low + high) / 2;

                if (overflows(element, mid)) {
                    high = mid;
                } else {
                    low = mid;
                }
            }

            size = `${overflows(element, low) ? min : low}px`;
        }

        if (width === lastWidth && size === lastSize) {
            element.style.fontSize = size;

            return;
        }

        lastWidth = width;
        lastSize = size;
        element.style.fontSize = size;
    };

    const schedule = () => {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(fit);
    };

    onMounted(() => {
        schedule();
        const parent = target.value?.parentElement;

        if (parent) {
            observer = new ResizeObserver(schedule);
            observer.observe(parent);
        }

        media = window.matchMedia(mediaQuery);
        media.addEventListener('change', schedule);
    });

    watch(source, schedule, { flush: 'post' });

    onBeforeUnmount(() => {
        cancelAnimationFrame(frame);
        observer?.disconnect();
        media?.removeEventListener('change', schedule);
    });
}
