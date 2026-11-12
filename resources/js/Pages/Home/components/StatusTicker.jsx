import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { cn } from '@/lib/utils';
import { poStatuses } from '@/data/landingContent';

gsap.registerPlugin(ScrollTrigger);

const FINE_POINTER_QUERY = '(hover: hover) and (pointer: fine)';
const DOT_COLORS = ['bg-brand-lime', 'bg-brand-cyan', 'bg-brand-pink', 'bg-brand-orange', 'bg-brand-purple'];
const SLIDE_DISTANCE_PERCENT = 30;

function TickerRow({ rowRef, statuses, onItemEnter, onItemLeave, hasCustomCursor }) {
    const renderList = (isDuplicate) => (
        <ul className="flex shrink-0 items-center gap-10 pr-10 desk:gap-16 desk:pr-16" aria-hidden={isDuplicate || undefined}>
            {statuses.map((status, index) => (
                <li
                    key={status}
                    onMouseEnter={() => onItemEnter(status)}
                    onMouseLeave={onItemLeave}
                    className={cn('flex items-center gap-4 type-heading-l whitespace-nowrap', hasCustomCursor && 'cursor-none')}
                >
                    <span className={cn('size-4 shrink-0 rounded-full desk:size-6', DOT_COLORS[index % DOT_COLORS.length])} />
                    {status}
                </li>
            ))}
        </ul>
    );

    return (
        <div className="overflow-hidden">
            <div ref={rowRef} className="flex w-max will-change-transform">
                {renderList(false)}
                {renderList(true)}
            </div>
        </div>
    );
}

/**
 * Horizontal slide: two rows of PO statuses move sideways in opposite directions while the page
 * scrolls vertically (GSAP ScrollTrigger, scrubbed). On fine pointers a custom cursor follows the mouse.
 */
export default function StatusTicker() {
    const [hasCustomCursor, setHasCustomCursor] = useState(false);
    const [hoveredStatus, setHoveredStatus] = useState(null);
    const rootRef = useRef(null);
    const firstRowRef = useRef(null);
    const secondRowRef = useRef(null);
    const cursorRef = useRef(null);
    const half = Math.ceil(poStatuses.length / 2);

    useLayoutEffect(() => {
        const mm = gsap.matchMedia();

        mm.add('(prefers-reduced-motion: no-preference)', () => {
            const scrollTrigger = { trigger: rootRef.current, start: 'top bottom', end: 'bottom top', scrub: 0.6 };
            gsap.fromTo(firstRowRef.current, { xPercent: 0 }, { xPercent: -SLIDE_DISTANCE_PERCENT, ease: 'none', scrollTrigger });
            gsap.fromTo(secondRowRef.current, { xPercent: -SLIDE_DISTANCE_PERCENT }, { xPercent: 0, ease: 'none', scrollTrigger });
        });

        return () => mm.revert();
    }, []);

    useEffect(() => {
        const mediaQuery = window.matchMedia(FINE_POINTER_QUERY);
        const onChange = () => setHasCustomCursor(mediaQuery.matches);
        onChange();
        mediaQuery.addEventListener('change', onChange);

        return () => mediaQuery.removeEventListener('change', onChange);
    }, []);

    useEffect(() => {
        if (!hasCustomCursor) {
            return undefined;
        }

        let frame;
        const onMouseMove = (event) => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(() => {
                if (cursorRef.current) {
                    cursorRef.current.style.transform = `translate3d(${event.clientX}px, ${event.clientY}px, 0)`;
                }
            });
        };
        window.addEventListener('mousemove', onMouseMove, { passive: true });

        return () => {
            window.removeEventListener('mousemove', onMouseMove);
            cancelAnimationFrame(frame);
        };
    }, [hasCustomCursor]);

    const rowProps = {
        hasCustomCursor,
        onItemEnter: setHoveredStatus,
        onItemLeave: () => setHoveredStatus(null),
    };

    return (
        <div ref={rootRef} className="flex flex-col gap-4 desk:gap-6">
            <TickerRow rowRef={firstRowRef} statuses={poStatuses.slice(0, half)} {...rowProps} />
            <TickerRow rowRef={secondRowRef} statuses={poStatuses.slice(half)} {...rowProps} />

            {hasCustomCursor && (
                <div
                    ref={cursorRef}
                    aria-hidden="true"
                    className={cn('pointer-events-none fixed top-0 left-0 z-[100] will-change-transform', hoveredStatus ? 'block' : 'hidden')}
                >
                    <span className="block -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand-offblack px-4 py-2 text-[13px] font-medium whitespace-nowrap text-brand-lime shadow-soft-md">
                        Status PO · {hoveredStatus}
                    </span>
                </div>
            )}
        </div>
    );
}
