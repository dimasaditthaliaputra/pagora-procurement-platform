import { useEffect, useRef, useState } from 'react';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import { cn } from '@/lib/utils';
import { workflowSteps } from '@/data/landingContent';
import { useInView } from '@/hooks/use-in-view';
import { useReducedMotion } from '@/hooks/use-reduced-motion';
import LandingImage from './LandingImage';

const AUTOPLAY_MS = 5000;
const SWIPE_THRESHOLD_PX = 40;

/**
 * Expandable carousel of procurement steps (DESIGN.md §6.5): the active slide widens,
 * autoplays every 5 seconds with a progress dot, and pauses while hovered.
 */
export default function WorkflowCarousel() {
    const [activeIndex, setActiveIndex] = useState(0);
    const [isPaused, setIsPaused] = useState(false);
    const rootRef = useRef(null);
    const trackRef = useRef(null);
    const progressRef = useRef(null);
    const elapsedRef = useRef(0);
    const swipeStartX = useRef(null);
    const isInView = useInView(rootRef, { threshold: 0.3 });
    const prefersReducedMotion = useReducedMotion();
    const lastIndex = workflowSteps.length - 1;

    const goTo = (index) => {
        elapsedRef.current = 0;
        setActiveIndex((index + workflowSteps.length) % workflowSteps.length);
    };

    useEffect(() => {
        trackRef.current?.style.setProperty('--index', String(activeIndex));
    }, [activeIndex]);

    useEffect(() => {
        if (prefersReducedMotion || !isInView || isPaused) {
            return undefined;
        }

        let frame;
        const startedAt = performance.now() - elapsedRef.current;
        const tick = (now) => {
            elapsedRef.current = now - startedAt;
            if (progressRef.current) {
                progressRef.current.style.width = `${Math.min(elapsedRef.current / AUTOPLAY_MS, 1) * 100}%`;
            }
            if (elapsedRef.current >= AUTOPLAY_MS) {
                elapsedRef.current = 0;
                setActiveIndex((current) => (current + 1) % workflowSteps.length);
                return;
            }
            frame = requestAnimationFrame(tick);
        };
        frame = requestAnimationFrame(tick);

        return () => cancelAnimationFrame(frame);
    }, [activeIndex, isPaused, isInView, prefersReducedMotion]);

    const onPointerUp = (event) => {
        if (swipeStartX.current === null) {
            return;
        }
        const distance = event.clientX - swipeStartX.current;
        swipeStartX.current = null;
        if (Math.abs(distance) >= SWIPE_THRESHOLD_PX) {
            goTo(activeIndex + (distance < 0 ? 1 : -1));
        }
    };

    return (
        <div ref={rootRef} role="region" aria-roledescription="carousel" aria-label="Alur pengadaan" className="mt-10 desk:mt-14">
            <div
                onPointerDown={(event) => {
                    swipeStartX.current = event.clientX;
                }}
                onPointerUp={onPointerUp}
                className="-my-6 touch-pan-y overflow-hidden py-6"
            >
                <ol
                    ref={trackRef}
                    className={cn(
                        'flex gap-[var(--gap)] transition-transform duration-500 ease-out-quick',
                        '[--active-w:min(340px,82vw)] [--gap:16px] [--index:0] [--slide-w:220px]',
                        'desk:[--active-w:560px] desk:[--gap:20px] desk:[--slide-w:260px]',
                        'ml-[calc(50%-var(--active-w)/2)] desk:ml-12 wide:ml-[calc((100%-1300px)/2)]',
                        'translate-x-[calc(var(--index)*-1*(var(--slide-w)+var(--gap)))]',
                    )}
                >
                    {workflowSteps.map((step, index) => {
                        const isActive = index === activeIndex;

                        return (
                            <li
                                key={step.title}
                                aria-roledescription="slide"
                                aria-label={`Langkah ${index + 1} dari ${workflowSteps.length}`}
                                aria-current={isActive ? 'step' : undefined}
                                onMouseEnter={() => isActive && setIsPaused(true)}
                                onMouseLeave={() => isActive && setIsPaused(false)}
                                className={cn(
                                    'relative flex h-[440px] shrink-0 flex-col overflow-hidden rounded-small border-2 p-5 transition-[width,border-color] duration-500 ease-out-quick desk:h-[480px] desk:rounded-medium desk:p-6',
                                    isActive ? 'conic-border w-[var(--active-w)] shadow-soft-md' : 'w-[var(--slide-w)] border-warm-80 bg-white shadow-soft-xs',
                                )}
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <span className="type-body-xs font-medium tabular-nums text-warm-60">
                                        {String(index + 1).padStart(2, '0')}
                                    </span>
                                    <span
                                        className={cn(
                                            'truncate rounded-full px-3 py-1 text-[12px] font-medium text-warm-50',
                                            isActive ? 'bg-white' : 'bg-brand-offwhite',
                                        )}
                                    >
                                        {step.role}
                                    </span>
                                </div>
                                <h3 className="mt-4 type-heading-xs">{step.title}</h3>
                                <p
                                    className={cn(
                                        'mt-3 w-[calc(var(--active-w)-48px)] type-body-s text-warm-60 transition-opacity',
                                        isActive ? 'opacity-100 delay-200 duration-500' : 'opacity-0 duration-150',
                                    )}
                                >
                                    {step.description}
                                </p>
                                <div
                                    className={cn(
                                        'relative mt-auto grid h-[180px] place-items-center overflow-hidden rounded-small desk:h-[220px]',
                                        step.color,
                                    )}
                                >
                                    <step.icon className="size-10 text-brand-offblack/40" aria-hidden="true" />
                                    <LandingImage
                                        name={step.image}
                                        className={cn(
                                            'transition-[transform,filter] duration-700 ease-out-quick',
                                            isActive ? 'scale-100 saturate-100' : 'scale-105 saturate-[.55]',
                                        )}
                                    />
                                    <span
                                        aria-hidden="true"
                                        className="absolute bottom-3 left-3 grid size-11 place-items-center rounded-full bg-white/90 text-brand-offblack shadow-soft-sm backdrop-blur-[6px]"
                                    >
                                        <step.icon className="size-5" />
                                    </span>
                                </div>
                                {!isActive && (
                                    <button
                                        type="button"
                                        onClick={() => goTo(index)}
                                        className="absolute inset-0 rounded-[inherit]"
                                        aria-label={`Lihat langkah ${index + 1}: ${step.title}`}
                                    />
                                )}
                            </li>
                        );
                    })}
                </ol>
            </div>

            <div className="landing-container mt-6 flex items-center justify-between gap-6">
                <div className="flex items-center gap-2">
                    {workflowSteps.map((step, index) => {
                        const isActive = index === activeIndex;

                        return (
                            <button
                                key={step.title}
                                type="button"
                                onClick={() => goTo(index)}
                                aria-label={`Ke langkah ${index + 1}`}
                                aria-current={isActive || undefined}
                                className={cn(
                                    'relative h-2 overflow-hidden rounded-full transition-all duration-300',
                                    isActive ? 'w-10 bg-warm-80' : 'w-2',
                                    !isActive && (index < activeIndex ? 'bg-brand-offblack' : 'bg-warm-80'),
                                )}
                            >
                                {isActive && (
                                    <span
                                        ref={progressRef}
                                        className={cn('absolute inset-y-0 left-0 w-0 rounded-full bg-brand-offblack', prefersReducedMotion && 'w-full')}
                                    />
                                )}
                            </button>
                        );
                    })}
                </div>
                <div className="flex gap-2">
                    <button
                        type="button"
                        onClick={() => goTo(activeIndex - 1)}
                        aria-label="Langkah sebelumnya"
                        className="grid size-11 place-items-center rounded-full border border-brand-offblack transition-colors duration-200 hover:border-transparent hover:bg-brand-lime"
                    >
                        <ArrowLeft className="size-5" />
                    </button>
                    <button
                        type="button"
                        onClick={() => goTo(activeIndex === lastIndex ? 0 : activeIndex + 1)}
                        aria-label="Langkah berikutnya"
                        className="grid size-11 place-items-center rounded-full border border-brand-offblack transition-colors duration-200 hover:border-transparent hover:bg-brand-lime"
                    >
                        <ArrowRight className="size-5" />
                    </button>
                </div>
            </div>
        </div>
    );
}
