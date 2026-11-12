import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';
import { features } from '@/data/landingContent';
import LandingButton from './LandingButton';
import ProductMockup from './ProductMockup';
import { useSmoothScroll } from './SmoothScrollProvider';

gsap.registerPlugin(ScrollTrigger);

const PANEL_COLORS = {
    'harga-pagu': 'bg-brand-lime',
    negosiasi: 'bg-brand-pink',
    pengiriman: 'bg-brand-orange',
    inspeksi: 'bg-brand-purple',
    matching: 'bg-brand-cyan',
};
const HASH_PREFIX = '#fitur-';
const PIN_QUERY = '(min-width: 1092px) and (min-height: 760px) and (prefers-reduced-motion: no-preference)';
const SCROLL_PER_TAB_VH = 0.6;
const LAST_INDEX = features.length - 1;

/** Scroll position inside the pinned range where tab `index` is shown. */
const tabScrollPosition = (trigger, index) => trigger.start + (index / LAST_INDEX) * (trigger.end - trigger.start) + 1;

/**
 * Feature tabs with a sliding pill (DESIGN.md §6.7).
 * Pinned section (desktop): GSAP ScrollTrigger pins the showcase and scroll progress picks the tab;
 * clicking a tab scrolls to that tab's point. Smaller screens keep plain clickable tabs.
 */
export default function FeatureTabsShowcase() {
    const [activeIndex, setActiveIndex] = useState(0);
    const [isPillReady, setIsPillReady] = useState(false);
    const [isPinned, setIsPinned] = useState(false);
    const pinRef = useRef(null);
    const tablistRef = useRef(null);
    const pillRef = useRef(null);
    const tabRefs = useRef([]);
    const triggerRef = useRef(null);
    const progressRef = useRef(null);
    const { scrollTo } = useSmoothScroll();
    const activeFeature = features[activeIndex];

    useLayoutEffect(() => {
        const movePill = () => {
            const tab = tabRefs.current[activeIndex];
            if (!tab || !pillRef.current) {
                return;
            }
            pillRef.current.style.width = `${tab.offsetWidth}px`;
            pillRef.current.style.transform = `translateX(${tab.offsetLeft}px)`;
        };

        movePill();
        setIsPillReady(true);

        const observer = new ResizeObserver(movePill);
        observer.observe(tablistRef.current);

        return () => observer.disconnect();
    }, [activeIndex]);

    useLayoutEffect(() => {
        const mm = gsap.matchMedia();

        mm.add(PIN_QUERY, () => {
            setIsPinned(true);
            triggerRef.current = ScrollTrigger.create({
                trigger: pinRef.current,
                start: 'top 104px',
                end: () => `+=${LAST_INDEX * window.innerHeight * SCROLL_PER_TAB_VH}`,
                pin: true,
                anticipatePin: 1,
                invalidateOnRefresh: true,
                onUpdate: (self) => {
                    setActiveIndex(Math.round(self.progress * LAST_INDEX));
                    if (progressRef.current) {
                        progressRef.current.style.transform = `scaleX(${self.progress})`;
                    }
                },
            });

            return () => {
                triggerRef.current = null;
                setIsPinned(false);
            };
        });

        return () => mm.revert();
    }, []);

    const selectTab = (index, shouldFocus = false) => {
        const trigger = triggerRef.current;
        if (trigger) {
            scrollTo(tabScrollPosition(trigger, index));
        } else {
            setActiveIndex(index);
            tabRefs.current[index]?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        }
        if (shouldFocus) {
            tabRefs.current[index]?.focus({ preventScroll: true });
        }
    };

    useEffect(() => {
        const openFromHash = (hash) => {
            const index = features.findIndex((feature) => `${HASH_PREFIX}${feature.id}` === hash);
            if (index === -1) {
                return;
            }
            if (triggerRef.current) {
                scrollTo(tabScrollPosition(triggerRef.current, index));
                return;
            }
            setActiveIndex(index);
            scrollTo(pinRef.current, { offset: -110 });
        };
        const onAnchor = (event) => openFromHash(event.detail);

        openFromHash(window.location.hash);
        window.addEventListener('landing:anchor', onAnchor);

        return () => window.removeEventListener('landing:anchor', onAnchor);
    }, [scrollTo]);

    const onKeyDown = (event) => {
        const keyTargets = {
            ArrowRight: activeIndex === LAST_INDEX ? 0 : activeIndex + 1,
            ArrowLeft: activeIndex === 0 ? LAST_INDEX : activeIndex - 1,
            Home: 0,
            End: LAST_INDEX,
        };

        if (event.key in keyTargets) {
            event.preventDefault();
            selectTab(keyTargets[event.key], true);
        }
    };

    return (
        <div className="mt-10 desk:mt-14">
            <div ref={pinRef} className="landing-container">
                <div data-lenis-prevent-horizontal className="-mx-1 flex justify-start overflow-x-auto px-1 pb-1 [scrollbar-width:none] desk:justify-center">
                    <div
                        ref={tablistRef}
                        role="tablist"
                        aria-label="Fitur PAGORA"
                        onKeyDown={onKeyDown}
                        className="relative inline-flex shrink-0 rounded-full border border-warm-80 bg-white p-1 shadow-soft-xs"
                    >
                        <span
                            ref={pillRef}
                            aria-hidden="true"
                            className={cn(
                                'absolute top-1 bottom-1 left-0 rounded-full bg-brand-offblack',
                                isPillReady && 'transition-[transform,width] duration-[320ms] ease-pill will-change-[transform,width]',
                            )}
                        />
                        {features.map((feature, index) => {
                            const isActive = index === activeIndex;

                            return (
                                <button
                                    key={feature.id}
                                    ref={(element) => {
                                        tabRefs.current[index] = element;
                                    }}
                                    type="button"
                                    role="tab"
                                    id={`tab-${feature.id}`}
                                    aria-selected={isActive}
                                    aria-controls={`panel-${feature.id}`}
                                    tabIndex={isActive ? 0 : -1}
                                    onClick={() => selectTab(index)}
                                    className={cn(
                                        'relative z-10 flex h-10 items-center gap-2 rounded-full px-4 type-body-xs font-medium whitespace-nowrap transition-colors duration-[320ms]',
                                        isActive ? 'text-white' : 'text-brand-offblack hover:text-warm-60',
                                    )}
                                >
                                    <feature.icon className="size-4" />
                                    {feature.tab}
                                </button>
                            );
                        })}
                    </div>
                </div>

                <div
                    id={`panel-${activeFeature.id}`}
                    role="tabpanel"
                    aria-labelledby={`tab-${activeFeature.id}`}
                    className="relative mt-6 grid overflow-hidden rounded-small bg-white shadow-soft-sm desk:min-h-[500px] desk:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)] desk:rounded-medium"
                >
                    <div key={`copy-${activeFeature.id}`} className="flex animate-fade-in-up flex-col p-6 tab:p-8 desk:p-12">
                        <span className="self-start rounded-full bg-brand-offwhite px-3 py-1.5 type-eyebrow text-warm-50">
                            {activeFeature.role}
                        </span>
                        <h3 className="mt-5 type-heading-s">{activeFeature.title}</h3>
                        <p className="mt-4 type-body-m text-warm-60">{activeFeature.description}</p>
                        <ul className="mt-6 flex flex-col gap-3">
                            {activeFeature.points.map((point) => (
                                <li key={point} className="flex items-center gap-3 type-body-s">
                                    <span className="grid size-6 shrink-0 place-items-center rounded-full bg-brand-lime">
                                        <Check className="size-3.5" />
                                    </span>
                                    {point}
                                </li>
                            ))}
                        </ul>
                        <LandingButton href="/register" variant="tertiary" className="mt-8 self-start desk:mt-auto">
                            Coba di perusahaan Anda
                        </LandingButton>
                    </div>
                    <div
                        className={cn(
                            'flex items-center justify-center p-6 transition-colors duration-500 ease-standard tab:p-10',
                            PANEL_COLORS[activeFeature.id],
                        )}
                    >
                        <div key={`mockup-${activeFeature.id}`} className="h-[340px] w-full max-w-[420px] animate-fade-in-up [animation-delay:60ms]">
                            <ProductMockup type={activeFeature.mockup} />
                        </div>
                    </div>
                    {isPinned && (
                        <span
                            ref={progressRef}
                            aria-hidden="true"
                            className="absolute inset-x-0 bottom-0 h-1 origin-left bg-brand-offblack [transform:scaleX(0)]"
                        />
                    )}
                </div>
            </div>
        </div>
    );
}
