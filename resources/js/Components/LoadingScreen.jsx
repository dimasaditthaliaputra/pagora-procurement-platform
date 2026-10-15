import React, { useRef, useEffect } from 'react';
import gsap from 'gsap';
import LogoSvg from '../../assets/logo/logo.svg';

// Continuous single path that loops through the entire PAGORA logo geometry:
// Top of P -> Arch of P -> Green Leaf -> Warehouse Roof & Wall -> 3D Orbit Arrow -> Stem of P -> Base -> Back to Start.
const LOGO_CONTOUR_PATH = 
    'M 180 180 ' +
    'L 840 180 ' +
    'C 1120 180, 1140 560, 860 620 ' +
    'C 980 460, 940 380, 780 390 ' +
    'L 560 240 ' +
    'L 360 390 ' +
    'L 360 640 ' +
    'C 200 660, 40 700, 40 760 ' +
    'C 60 960, 480 1020, 840 890 ' +
    'C 1060 820, 1180 660, 1240 500 ' +
    'L 1160 560 ' +
    'C 900 700, 420 700, 360 700 ' +
    'L 360 1100 ' +
    'L 180 1200 ' +
    'Z';

export default function LoadingScreen({ onStartReveal, onComplete }) {
    const containerRef = useRef(null);
    const svgRef = useRef(null);
    const drawStrokeRef = useRef(null);
    const maskStrokeRef = useRef(null);
    const maskCircleRef = useRef(null);
    const penDotRef = useRef(null);
    const brandTextRef = useRef(null);
    const logoGroupRef = useRef(null);

    useEffect(() => {
        // Prevent body scrolling during loading
        const originalOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        // Check prefers-reduced-motion
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        const ctx = gsap.context(() => {
            if (prefersReducedMotion) {
                // Accessible immediate fallback
                const tl = gsap.timeline({
                    onComplete: () => {
                        document.body.style.overflow = originalOverflow;
                        if (onComplete) onComplete();
                    }
                });

                if (onStartReveal) onStartReveal();
                tl.to(containerRef.current, {
                    opacity: 0,
                    duration: 0.35,
                    ease: 'power2.inOut',
                    delay: 0.2
                });
                return;
            }

            const pathEl = drawStrokeRef.current;
            const maskPathEl = maskStrokeRef.current;
            if (!pathEl || !maskPathEl) return;

            const pathLength = pathEl.getTotalLength();

            // Setup initial stroke dash properties
            pathEl.style.strokeDasharray = `${pathLength} ${pathLength}`;
            pathEl.style.strokeDashoffset = pathLength;
            maskPathEl.style.strokeDasharray = `${pathLength} ${pathLength}`;
            maskPathEl.style.strokeDashoffset = pathLength;

            // Position pen point initially at starting point (180, 180)
            const startPt = pathEl.getPointAtLength(0);
            if (penDotRef.current) {
                penDotRef.current.setAttribute('transform', `translate(${startPt.x}, ${startPt.y})`);
            }

            const tl = gsap.timeline({
                onComplete: () => {
                    document.body.style.overflow = originalOverflow;
                    if (onComplete) onComplete();
                }
            });

            // Step 1: Initial dot appears at starting point (•)
            tl.fromTo(penDotRef.current, 
                { scale: 0, opacity: 0 }, 
                { scale: 1, opacity: 1, duration: 0.32, ease: 'back.out(2)' }
            );

            // Step 2: The line starts drawing continuously from the dot
            const drawProxy = { progress: 0 };
            tl.to(drawProxy, {
                progress: 1,
                duration: 1.5,
                ease: 'power1.inOut',
                onUpdate: () => {
                    const p = drawProxy.progress;
                    const currentOffset = (1 - p) * pathLength;

                    // Update stroke dashoffsets
                    if (pathEl) pathEl.style.strokeDashoffset = currentOffset;
                    if (maskPathEl) maskPathEl.style.strokeDashoffset = currentOffset;

                    // Move pen point along the path
                    if (penDotRef.current) {
                        const pt = pathEl.getPointAtLength(p * pathLength);
                        penDotRef.current.setAttribute('transform', `translate(${pt.x}, ${pt.y})`);
                    }

                    // Once drawing passes 65%, expand mask circle to unveil 100% of the authentic logo
                    if (maskCircleRef.current) {
                        if (p > 0.65) {
                            const expandFactor = (p - 0.65) / 0.35;
                            maskCircleRef.current.setAttribute('r', expandFactor * 1050);
                        } else {
                            maskCircleRef.current.setAttribute('r', 0);
                        }
                    }

                    // Once drawing passes 85%, gently dissolve the guiding stroke & pen dot
                    if (p > 0.85) {
                        const fadeFactor = 1 - (p - 0.85) / 0.15;
                        if (pathEl) pathEl.style.opacity = fadeFactor;
                        if (penDotRef.current) penDotRef.current.style.opacity = fadeFactor;
                    }
                }
            });

            // Step 3: Logo is fully formed + subtle scale settle
            tl.to(logoGroupRef.current, {
                scale: 1.02,
                duration: 0.2,
                ease: 'power2.out'
            }, '-=0.1');

            tl.to(logoGroupRef.current, {
                scale: 1,
                duration: 0.2,
                ease: 'power2.inOut'
            });

            // Step 4: Brand name slides in cleanly underneath
            tl.to(brandTextRef.current, {
                opacity: 1,
                y: 0,
                duration: 0.35,
                ease: 'power2.out'
            }, '-=0.15');

            // Step 5: Short hold to admire the completed logo mark
            tl.to({}, { duration: 0.32 });

            // Step 6: Trigger Hero Slide Down simultaneously as the cream screen slides up
            tl.call(() => {
                if (onStartReveal) {
                    setTimeout(() => {
                        onStartReveal();
                    }, 0);
                }
            });

            // Step 7: Cream Loading Screen slides smoothly UP out of the viewport
            tl.to(containerRef.current, {
                yPercent: -100,
                duration: 0.82,
                ease: 'power3.inOut'
            });

        }, containerRef);

        return () => {
            document.body.style.overflow = originalOverflow;
            ctx.revert();
        };
    }, []);

    return (
        <div 
            ref={containerRef}
            className="fixed inset-0 z-100 bg-cream-paper flex flex-col items-center justify-center select-none overflow-hidden will-change-transform"
            style={{ backgroundColor: '#f5f1e4' }}
            aria-label="Loading PAGORA"
        >
            <div className="relative flex flex-col items-center justify-center p-6">
                {/* SVG Canvas for Path Drawing and Masked Authentic Logo */}
                <div ref={logoGroupRef} className="relative w-36 h-36 sm:w-48 sm:h-48 md:w-56 md:h-56 transform will-change-transform">
                    <svg
                        ref={svgRef}
                        viewBox="0 0 1280 1280"
                        className="w-full h-full drop-shadow-md overflow-visible"
                    >
                        <defs>
                            {/* Mask that dynamically reveals the full-color logo along the stroke path */}
                            <mask id="pagora-logo-draw-mask" maskUnits="userSpaceOnUse" x="0" y="0" width="1280" height="1280">
                                {/* Initially black (completely hidden) */}
                                <rect x="0" y="0" width="1280" height="1280" fill="#000000" />
                                
                                {/* The drawing path inside the mask (white reveals content) */}
                                <path
                                    ref={maskStrokeRef}
                                    d={LOGO_CONTOUR_PATH}
                                    fill="none"
                                    stroke="#FFFFFF"
                                    strokeWidth="190"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                />

                                {/* Expanding bloom circle at the end to guarantee 100% full unmasking */}
                                <circle
                                    ref={maskCircleRef}
                                    cx="640"
                                    cy="640"
                                    r="0"
                                    fill="#FFFFFF"
                                />
                            </mask>

                            {/* Beautiful gradient for the visible drawing stroke */}
                            <linearGradient id="pagora-draw-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stopColor="#015CB0" />
                                <stop offset="45%" stopColor="#14AAB1" />
                                <stop offset="100%" stopColor="#45A541" />
                            </linearGradient>

                            {/* Glow filter for pen dot */}
                            <filter id="pen-glow" x="-50%" y="-50%" width="200%" height="200%">
                                <feDropShadow dx="0" dy="2" stdDeviation="6" floodColor="#45A541" floodOpacity="0.65" />
                            </filter>
                        </defs>

                        {/* 1. Authentic Logo Revealed by Mask */}
                        <g mask="url(#pagora-logo-draw-mask)">
                            <image
                                href={LogoSvg}
                                xlinkHref={LogoSvg}
                                x="0"
                                y="0"
                                width="1280"
                                height="1280"
                                preserveAspectRatio="xMidYMid meet"
                            />
                        </g>

                        {/* 2. Visible Drawing Stroke */}
                        <path
                            ref={drawStrokeRef}
                            d={LOGO_CONTOUR_PATH}
                            fill="none"
                            stroke="url(#pagora-draw-grad)"
                            strokeWidth="24"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            className="pointer-events-none"
                        />

                        {/* 3. Leading Pen Dot (•) */}
                        <g ref={penDotRef} filter="url(#pen-glow)" className="pointer-events-none">
                            <circle r="22" fill="#45A541" opacity="0.25" />
                            <circle r="13" fill="#45A541" stroke="#FFFFFF" strokeWidth="4" />
                        </g>
                    </svg>
                </div>

                {/* Brand Name Typography */}
                <div 
                    ref={brandTextRef} 
                    className="flex flex-col items-center mt-7 text-center opacity-0 translate-y-3 pointer-events-none"
                >
                    <span className="font-semibold text-2xl sm:text-3xl tracking-[0.24em] text-ink-black uppercase font-sans">
                        PAGORA
                    </span>
                    <span className="text-[10px] sm:text-xs text-stone-gray tracking-[0.38em] font-medium mt-1.5 uppercase">
                        B2B E-Procurement
                    </span>
                </div>
            </div>
        </div>
    );
}
