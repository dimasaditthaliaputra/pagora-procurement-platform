import { createContext, useCallback, useContext, useEffect, useMemo, useRef } from 'react';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import Lenis from 'lenis';
import Snap from 'lenis/snap';
import 'lenis/dist/lenis.css';

gsap.registerPlugin(ScrollTrigger);

const SMOOTH_SCROLL_QUERY = '(hover: hover) and (pointer: fine) and (prefers-reduced-motion: no-preference)';
const NATIVE_SNAP_CLASS = 'lp-native-snap';

const SmoothScrollContext = createContext({
    scrollTo: () => {},
    setLocked: () => {},
});

export const useSmoothScroll = () => useContext(SmoothScrollContext);

/**
 * Smooth scrolling for the landing page.
 * - Mouse/trackpad: Lenis drives the scroll, Lenis Snap snaps to `[data-snap]` sections,
 *   and GSAP ScrollTrigger updates from Lenis on the shared GSAP ticker.
 * - Touch or reduced motion: no Lenis; native momentum scrolling with CSS `scroll-snap` (proximity).
 * Same-page `#anchor` links are scrolled here so every navigation path uses one scroller.
 */
export default function SmoothScrollProvider({ children }) {
    const lenisRef = useRef(null);

    useEffect(() => {
        const root = document.documentElement;
        const sections = Array.from(document.querySelectorAll('.landing-page [data-snap]'));

        if (!window.matchMedia(SMOOTH_SCROLL_QUERY).matches) {
            root.classList.add(NATIVE_SNAP_CLASS);

            return () => root.classList.remove(NATIVE_SNAP_CLASS);
        }

        const lenis = new Lenis({ lerp: 0.1, wheelMultiplier: 1, autoRaf: false });
        const snap = new Snap(lenis, { type: 'proximity', distanceThreshold: '25%', debounce: 400, duration: 0.9 });
        const removeSnaps = snap.addElements(sections, { align: 'start' });
        const onTick = (time) => lenis.raf(time * 1000);

        lenis.on('scroll', ScrollTrigger.update);
        gsap.ticker.add(onTick);
        gsap.ticker.lagSmoothing(0);
        lenisRef.current = lenis;
        ScrollTrigger.refresh();

        return () => {
            gsap.ticker.remove(onTick);
            gsap.ticker.lagSmoothing(500, 33);
            removeSnaps();
            snap.destroy();
            lenis.destroy();
            lenisRef.current = null;
        };
    }, []);

    const scrollTo = useCallback((target, { offset = 0, immediate = false } = {}) => {
        const element = typeof target === 'number' ? null : target;
        const top = element ? element.getBoundingClientRect().top + window.scrollY + offset : target + offset;

        if (lenisRef.current) {
            lenisRef.current.scrollTo(top, { immediate, duration: 1.2 });
            return;
        }

        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({ top, behavior: immediate || prefersReducedMotion ? 'auto' : 'smooth' });
    }, []);

    const setLocked = useCallback((isLocked) => {
        if (lenisRef.current) {
            isLocked ? lenisRef.current.stop() : lenisRef.current.start();
        }
        document.body.style.overflow = isLocked ? 'hidden' : '';
    }, []);

    useEffect(() => {
        const onClick = (event) => {
            const link = event.target.closest?.('a[href^="#"]');
            if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey) {
                return;
            }

            const hash = link.getAttribute('href');
            event.preventDefault();
            window.history.pushState(null, '', hash);

            const element = hash.length > 1 ? document.getElementById(hash.slice(1)) : null;
            if (element) {
                scrollTo(element);
                return;
            }
            window.dispatchEvent(new CustomEvent('landing:anchor', { detail: hash }));
        };

        document.addEventListener('click', onClick);

        return () => document.removeEventListener('click', onClick);
    }, [scrollTo]);

    const value = useMemo(() => ({ scrollTo, setLocked }), [scrollTo, setLocked]);

    return <SmoothScrollContext.Provider value={value}>{children}</SmoothScrollContext.Provider>;
}
