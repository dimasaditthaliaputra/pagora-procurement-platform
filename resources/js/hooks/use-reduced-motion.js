import { useEffect, useState } from 'react';

const QUERY = '(prefers-reduced-motion: reduce)';

/**
 * Whether the visitor asked the OS to reduce motion. Updates live when the setting changes.
 */
export function useReducedMotion() {
    const [prefersReducedMotion, setPrefersReducedMotion] = useState(
        () => typeof window !== 'undefined' && window.matchMedia(QUERY).matches,
    );

    useEffect(() => {
        const mediaQuery = window.matchMedia(QUERY);
        const onChange = () => setPrefersReducedMotion(mediaQuery.matches);

        mediaQuery.addEventListener('change', onChange);

        return () => mediaQuery.removeEventListener('change', onChange);
    }, []);

    return prefersReducedMotion;
}
