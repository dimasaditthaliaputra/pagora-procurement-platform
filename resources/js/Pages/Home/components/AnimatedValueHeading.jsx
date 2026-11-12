import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import { useInView } from '@/hooks/use-in-view';
import { useReducedMotion } from '@/hooks/use-reduced-motion';
import { Reveal } from './Reveal';

const COUNT_DURATION_MS = 2000;
const numberFormatter = (decimals) =>
    new Intl.NumberFormat('id-ID', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

const easeOutExpo = (progress) => (progress === 1 ? 1 : 1 - 2 ** (-10 * progress));

/**
 * Section heading with a number that counts up once it scrolls into view (DESIGN.md §6.2).
 * Both the start and end values are rendered invisibly first, so the line never shifts while counting.
 */
export default function AnimatedValueHeading({ before, value, after, description, action, theme = 'light', className }) {
    const { from, to, decimals = 0, prefix = '', suffix = '' } = value;
    const headingRef = useRef(null);
    const isInView = useInView(headingRef, { once: true });
    const prefersReducedMotion = useReducedMotion();
    const [current, setCurrent] = useState(from);
    const format = numberFormatter(decimals);
    const display = (number) => `${prefix}${format.format(number)}${suffix}`;

    useEffect(() => {
        if (!isInView) {
            return undefined;
        }
        if (prefersReducedMotion) {
            setCurrent(to);
            return undefined;
        }

        let frame;
        const startedAt = performance.now();
        const tick = (now) => {
            const progress = Math.min((now - startedAt) / COUNT_DURATION_MS, 1);
            setCurrent(from + (to - from) * easeOutExpo(progress));
            if (progress < 1) {
                frame = requestAnimationFrame(tick);
            }
        };
        frame = requestAnimationFrame(tick);

        return () => cancelAnimationFrame(frame);
    }, [isInView, prefersReducedMotion, from, to]);

    const isDark = theme === 'dark';

    return (
        <Reveal className={cn('landing-container flex flex-col items-center text-center', className)}>
            <h2 ref={headingRef} className="max-w-5xl type-heading-m whitespace-pre-line">
                {before}
                <span className="sr-only">{display(to)}</span>
                <span aria-hidden="true" className="relative inline-grid">
                    <span className="invisible col-start-1 row-start-1 whitespace-nowrap">{display(from)}</span>
                    <span className="invisible col-start-1 row-start-1 whitespace-nowrap">{display(to)}</span>
                    <span className="absolute top-0 right-0 whitespace-nowrap tabular-nums">{display(current)}</span>
                </span>
                {after}
            </h2>
            {description && (
                <p className={cn('mt-4 max-w-[660px] type-body-m desk:mt-6', isDark ? 'text-warm-80' : 'text-warm-60')}>{description}</p>
            )}
            {action && <div className="mt-6">{action}</div>}
        </Reveal>
    );
}
