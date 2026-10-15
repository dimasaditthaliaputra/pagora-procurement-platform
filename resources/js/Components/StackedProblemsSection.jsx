import React, { useRef, useEffect } from 'react';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { AlertTriangle } from 'lucide-react';
import { Card, CardTitle, CardDescription } from '@/Components/ui/card';
import SplitText from '@/Components/SplitText';

gsap.registerPlugin(ScrollTrigger);

export default function StackedProblemsSection({ problems = [] }) {
    const sectionRef = useRef(null);
    const cardsRef = useRef([]);

    useEffect(() => {
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReducedMotion) return;

        const ctx = gsap.context(() => {
            cardsRef.current.forEach((card, i) => {
                if (!card || i === problems.length - 1) return;

                ScrollTrigger.create({
                    trigger: card,
                    start: 'top 5rem',
                    end: `+=${card.offsetHeight}`,
                    scrub: true,
                    onUpdate: (self) => {
                        const progress = self.progress;
                        const scale = 1 - progress * 0.05;
                        const translateY = -progress * 20;
                        const opacity = 1 - progress * 0.35;
                        gsap.set(card, {
                            scale,
                            y: translateY,
                            opacity,
                        });
                    },
                });
            });
        }, sectionRef);

        return () => ctx.revert();
    }, [problems]);

    const accentColors = ['bg-coral-pop', 'bg-sky-pop', 'bg-sunshine-pop'];
    const tagBgColors = ['bg-coral-pop/10', 'bg-sky-pop/10', 'bg-sunshine-pop/10'];

    return (
        <section
            id="problems"
            ref={sectionRef}
            className="relative w-full bg-[#eae4d3] border-y border-sandstone/35"
            style={{ paddingTop: 'var(--section-py)', paddingBottom: 'var(--section-py)' }}
        >
            <div className="max-w-3xl mx-auto text-center px-4 sm:px-6 lg:px-8 mb-16 sm:mb-24">
                <SplitText
                    as="h2"
                    className="text-heading-xl font-bold text-ink-black tracking-heading-xl leading-heading-xl mb-5"
                >
                    Celah Operasional yang Menguras Anggaran Manufaktur
                </SplitText>

                <p className="text-body-lg text-stone-gray leading-body-lg font-normal max-w-2xl mx-auto">
                    Pengadaan bahan baku secara manual dan berbasis kepercayaan sepihak membuka ruang mark-up, ketidaksesuaian mutu, serta kerugian finansial yang tak terdeteksi.
                </p>
            </div>

            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                {problems.map((problem, idx) => (
                    <div
                        key={problem.id}
                        ref={(el) => (cardsRef.current[idx] = el)}
                        className="stacked-problem-card"
                        style={{ zIndex: 10 + idx }}
                    >
                        <Card className="flex flex-col bg-pure-white rounded-[2rem] border border-hairline-mist p-7 sm:p-9 shadow-sm hover:shadow-md transition-shadow duration-300">
                            <div className="flex items-center justify-between mb-5">
                                <span className="text-4xl sm:text-5xl font-bold text-ink-black/10">
                                    {problem.number}
                                </span>
                                <span className={`px-3.5 py-1.5 rounded-[50px] text-xs font-semibold text-ink-black border border-hairline-mist ${tagBgColors[idx] || 'bg-cream-paper'}`}>
                                    {problem.tag}
                                </span>
                            </div>

                            <CardTitle className="mb-3 text-heading-md font-semibold text-ink-black leading-heading-md tracking-tight">
                                {problem.title}
                            </CardTitle>

                            <CardDescription className="text-body-lg text-stone-gray leading-relaxed mb-6">
                                {problem.description}
                            </CardDescription>

                            <div className="mt-auto pt-6 border-t border-sandstone/50 space-y-4">
                                <div className="text-xs font-bold uppercase tracking-wide text-coral-pop flex items-center gap-2">
                                    <span className={`w-2.5 h-2.5 rounded-full ${accentColors[idx] || 'bg-coral-pop'} shrink-0`} />
                                    <span>{problem.impact}</span>
                                </div>

                                <div className="text-sm italic text-stone-gray bg-cream-paper/60 p-4 sm:p-5 rounded-2xl border border-sandstone/40">
                                    <p className="leading-relaxed">{problem.quote}</p>
                                </div>
                            </div>
                        </Card>
                    </div>
                ))}
            </div>
        </section>
    );
}
