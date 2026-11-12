import { useEffect, useRef, useState } from 'react';
import { ArrowRight } from 'lucide-react';
import { cn } from '@/lib/utils';
import { problems } from '@/data/landingContent';
import LandingImage from './LandingImage';

const HOVER_QUERY = '(hover: hover) and (prefers-reduced-motion: no-preference)';
const HOVER_RELEASE_DELAY_MS = 50;
const SWIPE_THRESHOLD_PX = 36;

/**
 * Horizontal accordion of problem → solution cards (DESIGN.md §6.8).
 * Desktop: the active card widens while the rest shrink to strips. Mobile: cards stack vertically.
 */
export default function ProblemAccordion() {
    const [activeIndex, setActiveIndex] = useState(0);
    const [hoverIndex, setHoverIndex] = useState(null);
    const [canHover, setCanHover] = useState(false);
    const triggerRefs = useRef([]);
    const releaseTimer = useRef(null);
    const swipeStartX = useRef(null);

    useEffect(() => {
        const mediaQuery = window.matchMedia(HOVER_QUERY);
        const onChange = () => setCanHover(mediaQuery.matches);
        onChange();
        mediaQuery.addEventListener('change', onChange);

        return () => {
            mediaQuery.removeEventListener('change', onChange);
            clearTimeout(releaseTimer.current);
        };
    }, []);

    const activate = (index, shouldFocus = false) => {
        setHoverIndex(null);
        setActiveIndex(index);
        if (shouldFocus) {
            triggerRefs.current[index]?.focus({ preventScroll: true });
        }
    };

    const onKeyDown = (event, index) => {
        const lastIndex = problems.length - 1;
        const keyTargets = {
            ArrowRight: (index + 1) % problems.length,
            ArrowDown: (index + 1) % problems.length,
            ArrowLeft: (index - 1 + problems.length) % problems.length,
            ArrowUp: (index - 1 + problems.length) % problems.length,
            Home: 0,
            End: lastIndex,
        };

        if (event.key in keyTargets) {
            event.preventDefault();
            activate(keyTargets[event.key], true);
        }
    };

    const onPointerEnter = (index) => {
        if (!canHover || index === activeIndex) {
            return;
        }
        clearTimeout(releaseTimer.current);
        setHoverIndex(index);
    };

    const onPointerLeave = () => {
        clearTimeout(releaseTimer.current);
        releaseTimer.current = setTimeout(() => setHoverIndex(null), HOVER_RELEASE_DELAY_MS);
    };

    const onPointerUp = (event) => {
        if (swipeStartX.current === null) {
            return;
        }
        const distance = event.clientX - swipeStartX.current;
        swipeStartX.current = null;
        if (Math.abs(distance) < SWIPE_THRESHOLD_PX) {
            return;
        }
        const nextIndex = Math.min(Math.max(activeIndex + (distance < 0 ? 1 : -1), 0), problems.length - 1);
        activate(nextIndex);
    };

    return (
        <div className="landing-container mt-10 desk:mt-14">
            <div
                onPointerDown={(event) => {
                    swipeStartX.current = event.clientX;
                }}
                onPointerUp={onPointerUp}
                className="flex flex-col gap-3 desk:h-[500px] desk:flex-row desk:gap-5"
            >
                {problems.map((item, index) => {
                    const isActive = index === activeIndex;
                    const isHovered = index === hoverIndex;
                    const panelId = `problem-${item.id}`;

                    return (
                        <article
                            key={item.id}
                            data-focus-theme="dark"
                            onPointerEnter={() => onPointerEnter(index)}
                            onPointerLeave={onPointerLeave}
                            className={cn(
                                'relative isolate flex flex-col overflow-hidden rounded-small text-white shadow-soft-sm transition-[flex-grow,flex-basis,min-width] ease-standard desk:rounded-medium',
                                item.color,
                                isActive ? 'min-h-[440px] duration-[420ms] desk:min-h-0 desk:min-w-0 desk:grow desk:basis-0' : 'duration-[240ms] desk:grow-0',
                                !isActive && (isHovered ? 'desk:basis-[124px]' : 'desk:basis-[100px]'),
                            )}
                        >
                            <LandingImage
                                name={item.image}
                                isDecorative
                                className={cn(
                                    '-z-10 transition-transform duration-700 ease-out-quick',
                                    isActive ? 'scale-100' : 'scale-110',
                                )}
                            />
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'absolute inset-0 -z-10 transition-opacity duration-500',
                                    'bg-gradient-to-t from-brand-offblack/90 via-brand-offblack/40 to-brand-offblack/25',
                                )}
                            />
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'absolute inset-0 -z-10 bg-brand-offblack/30 transition-opacity duration-500',
                                    isActive ? 'opacity-0' : 'opacity-100',
                                )}
                            />

                            <h3>
                                <button
                                    ref={(element) => {
                                        triggerRefs.current[index] = element;
                                    }}
                                    type="button"
                                    aria-expanded={isActive}
                                    aria-controls={panelId}
                                    onClick={() => activate(index)}
                                    onKeyDown={(event) => onKeyDown(event, index)}
                                    className={cn(
                                        'flex w-full items-center justify-between gap-4 rounded-[inherit] p-5 text-left focus-visible:outline-offset-[-6px] desk:p-6',
                                        !isActive && 'after:absolute after:inset-0',
                                    )}
                                >
                                    <span className="flex items-center gap-3">
                                        <span className="type-body-xs font-medium tabular-nums text-white/80">0{index + 1}</span>
                                        <span
                                            className={cn(
                                                'rounded-full px-3 py-1 type-body-xs font-medium whitespace-nowrap text-brand-offblack transition-opacity duration-200',
                                                item.color,
                                                !isActive && 'desk:opacity-0',
                                            )}
                                        >
                                            {item.label}
                                        </span>
                                    </span>
                                    <ArrowRight
                                        className={cn('size-5 shrink-0 transition-transform duration-300 desk:hidden', isActive && 'rotate-90')}
                                    />
                                </button>
                            </h3>

                            <span
                                aria-hidden="true"
                                className={cn(
                                    'pointer-events-none absolute bottom-6 left-1/2 hidden -translate-x-1/2 rotate-180 type-heading-xs whitespace-nowrap [writing-mode:vertical-rl] transition-opacity duration-200 desk:block',
                                    isActive ? 'opacity-0' : 'opacity-100',
                                )}
                            >
                                {item.label}
                            </span>

                            <div
                                id={panelId}
                                className={cn(
                                    'mt-auto px-5 pb-5 desk:absolute desk:inset-x-0 desk:bottom-0 desk:flex desk:min-w-[480px] desk:flex-col desk:px-8 desk:pb-8',
                                    'transition-[opacity,visibility] ease-standard',
                                    isActive
                                        ? 'visible opacity-100 delay-150 duration-300'
                                        : 'invisible hidden opacity-0 duration-100 desk:flex',
                                )}
                            >
                                <p className="max-w-[560px] type-heading-s">{item.problem}</p>
                                <p className="mt-3 max-w-[480px] type-body-s text-white/80">{item.detail}</p>
                                <div className="mt-6 max-w-[520px] rounded-small border border-white/15 bg-white/12 p-4 backdrop-blur-md desk:p-5">
                                    <p className="type-eyebrow text-white/70">Solusi PAGORA</p>
                                    <p className="mt-2 type-body-s font-medium">{item.solution}</p>
                                </div>
                            </div>
                        </article>
                    );
                })}
            </div>
        </div>
    );
}
