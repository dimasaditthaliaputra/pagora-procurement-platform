import React, { useRef, useEffect, Children } from 'react';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

export default function SplitText({
    children,
    as: Tag = 'h2',
    className = '',
    staggerDelay = 0.035,
    duration = 0.7,
    yOffset = 40,
    once = true,
    startThreshold = 'top 85%',
    ...props
}) {
    const containerRef = useRef(null);

    const text = typeof children === 'string'
        ? children
        : Children.toArray(children)
            .map(child => (typeof child === 'string' ? child : ''))
            .join('');

    const words = text.split(/\s+/).filter(Boolean);

    useEffect(() => {
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReducedMotion) return;

        const el = containerRef.current;
        if (!el) return;

        const wordEls = el.querySelectorAll('.split-word');
        if (!wordEls.length) return;

        gsap.set(wordEls, { opacity: 0, y: yOffset });

        const ctx = gsap.context(() => {
            gsap.to(wordEls, {
                opacity: 1,
                y: 0,
                duration,
                stagger: staggerDelay,
                ease: 'power3.out',
                scrollTrigger: {
                    trigger: el,
                    start: startThreshold,
                    once,
                },
            });
        }, el);

        return () => ctx.revert();
    }, [text, staggerDelay, duration, yOffset, once, startThreshold]);

    return (
        <Tag ref={containerRef} className={className} {...props}>
            {words.map((word, i) => (
                <React.Fragment key={i}>
                    <span className="split-word">{word}</span>
                    {i < words.length - 1 && ' '}
                </React.Fragment>
            ))}
        </Tag>
    );
}
