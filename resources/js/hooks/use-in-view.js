import { useEffect, useState } from 'react';

/**
 * Track whether an element is inside the viewport.
 * With `once`, the value stays true after the first time the element is seen.
 */
export function useInView(ref, { rootMargin = '0px', threshold = 0, once = false } = {}) {
    const [isInView, setIsInView] = useState(false);

    useEffect(() => {
        const element = ref.current;
        if (!element) {
            return undefined;
        }

        const observer = new IntersectionObserver(
            ([entry]) => {
                setIsInView(entry.isIntersecting);
                if (entry.isIntersecting && once) {
                    observer.disconnect();
                }
            },
            { rootMargin, threshold },
        );

        observer.observe(element);

        return () => observer.disconnect();
    }, [ref, rootMargin, threshold, once]);

    return isInView;
}
