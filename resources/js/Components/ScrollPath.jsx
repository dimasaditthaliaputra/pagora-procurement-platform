import React, { useRef, useEffect, useState } from 'react';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

export default function ScrollPath() {
    const svgRef = useRef(null);
    const pathRef = useRef(null);
    const [docHeight, setDocHeight] = useState(0);

    useEffect(() => {
        const updateHeight = () => {
            setDocHeight(document.documentElement.scrollHeight);
        };

        updateHeight();

        const resizeObserver = new ResizeObserver(updateHeight);
        resizeObserver.observe(document.body);

        return () => resizeObserver.disconnect();
    }, []);

    useEffect(() => {
        if (!docHeight || !pathRef.current) return;

        const pathEl = pathRef.current;
        const pathLength = pathEl.getTotalLength();

        gsap.set(pathEl, {
            strokeDasharray: pathLength,
            strokeDashoffset: pathLength,
        });

        const ctx = gsap.context(() => {
            gsap.to(pathEl, {
                strokeDashoffset: 0,
                ease: 'none',
                scrollTrigger: {
                    trigger: document.documentElement,
                    start: 'top top',
                    end: 'bottom bottom',
                    scrub: 0.3,
                },
            });
        });

        return () => ctx.revert();
    }, [docHeight]);

    if (!docHeight) return null;

    const w = 1440;
    const h = docHeight;
    const cx = w / 2;

    const pathD = [
        `M ${cx} 0`,
        `C ${cx + 300} ${h * 0.08}, ${cx - 400} ${h * 0.15}, ${cx - 200} ${h * 0.2}`,
        `S ${cx + 350} ${h * 0.28}, ${cx + 100} ${h * 0.35}`,
        `S ${cx - 350} ${h * 0.42}, ${cx - 50} ${h * 0.48}`,
        `S ${cx + 400} ${h * 0.55}, ${cx + 150} ${h * 0.62}`,
        `S ${cx - 300} ${h * 0.7}, ${cx - 100} ${h * 0.75}`,
        `S ${cx + 350} ${h * 0.82}, ${cx + 50} ${h * 0.88}`,
        `S ${cx - 200} ${h * 0.94}, ${cx} ${h}`,
    ].join(' ');

    return (
        <svg
            ref={svgRef}
            className="pointer-events-none fixed inset-0 w-full h-full"
            style={{ zIndex: 10 }}
            viewBox={`0 0 ${w} ${h}`}
            preserveAspectRatio="none"
            aria-hidden="true"
        >
            <defs>
                <linearGradient id="scroll-path-gradient" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" stopColor="#8ed462" />
                    <stop offset="50%" stopColor="#2ba0ff" />
                    <stop offset="100%" stopColor="#ff705d" />
                </linearGradient>
            </defs>
            <path
                ref={pathRef}
                d={pathD}
                className="scroll-path-line"
                stroke="url(#scroll-path-gradient)"
            />
        </svg>
    );
}
