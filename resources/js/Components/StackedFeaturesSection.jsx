import React, { useRef, useEffect, useState } from 'react';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import {
    AlertTriangle,
} from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import SplitText from '@/Components/SplitText';

gsap.registerPlugin(ScrollTrigger);

export default function StackedFeaturesSection({ features = [] }) {
    const pinContainerRef = useRef(null);
    const [activeCardIndex, setActiveCardIndex] = useState(0);

    useEffect(() => {
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        const ctx = gsap.context(() => {
            const cards = gsap.utils.toArray('.stacked-feature-card');
            if (!cards.length) return;

            const scrollDistance = (cards.length - 1) * 100;

            const tl = gsap.timeline({
                scrollTrigger: {
                    trigger: pinContainerRef.current,
                    pin: true,
                    start: 'top top',
                    end: `+=${scrollDistance}%`,
                    scrub: prefersReducedMotion ? false : 0.8,
                    anticipatePin: 1,
                    onUpdate: (self) => {
                        const idx = Math.min(
                            cards.length - 1,
                            Math.floor(self.progress * cards.length)
                        );
                        setActiveCardIndex(idx);
                    }
                }
            });

            cards.forEach((card, i) => {
                if (i > 0) {
                    gsap.set(card, {
                        yPercent: 110,
                        opacity: 0,
                        scale: 0.92
                    });

                    tl.to(card, {
                        yPercent: 0,
                        opacity: 1,
                        scale: 1,
                        duration: 1,
                        ease: 'power2.out'
                    });

                    const prevCard = cards[i - 1];
                    tl.to(prevCard, {
                        scale: 0.94,
                        yPercent: -4,
                        opacity: 0.15,
                        duration: 1,
                        ease: 'power2.out'
                    }, '<');
                }
            });
        }, pinContainerRef);

        return () => ctx.revert();
    }, [features]);

    return (
        <section
            id="features"
            ref={pinContainerRef}
            className="relative w-full min-h-screen bg-fresh-grass border-b border-fresh-grass/20 flex items-center justify-center overflow-hidden"
        >
            <div className="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-12 sm:py-16 lg:py-20">
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-16 items-center">
                    <div className="lg:col-span-5 flex flex-col justify-center items-center text-center space-y-5 sm:space-y-7 my-auto">
                        <SplitText
                            as="h2"
                            className="text-heading-xl font-bold text-ink-black tracking-heading-xl leading-heading-xl max-w-xl mx-auto"
                        >
                            Arsitektur Sistem: Cerdas, Objektif, dan Terotomasi
                        </SplitText>

                        <div className="flex items-center justify-center gap-2 pt-2">
                            {features.map((_, i) => (
                                <span
                                    key={i}
                                    className={`h-2 rounded-full transition-all duration-500 ease-out ${activeCardIndex === i
                                            ? 'w-10 bg-fresh-grass'
                                            : 'w-2 bg-hairline-mist'
                                    }`}
                                />
                            ))}
                        </div>
                    </div>

                    <div className="lg:col-span-7 relative w-full h-[490px] sm:h-[540px] lg:h-[560px] flex items-center justify-center">
                        {features.map((feature, idx) => (
                            <div
                                key={feature.id}
                                className="stacked-feature-card absolute inset-0 bg-pure-white rounded-[2rem] border border-hairline-mist p-6 sm:p-8 lg:p-10 flex flex-col justify-between select-none shadow-sm"
                                style={{ zIndex: idx + 10 }}
                            >
                                <div>
                                    <div className="flex flex-wrap items-center justify-between gap-2 mb-5">
                                        <Badge
                                            variant={feature.tagColor || 'green'}
                                            pill
                                            dotColor={
                                                feature.tagColor === 'coral' ? 'bg-coral-pop' :
                                                feature.tagColor === 'sky' ? 'bg-sky-pop' :
                                                feature.tagColor === 'yellow' ? 'bg-sunshine-pop' :
                                                'bg-fresh-grass'
                                            }
                                        >
                                            {feature.badge}
                                        </Badge>
                                        <span className="text-xs font-mono font-semibold text-stone-gray">
                                            Kapabilitas #{idx + 1}
                                        </span>
                                    </div>

                                    <h3 className="text-heading-md font-semibold text-ink-black tracking-tight mb-3 leading-snug">
                                        {feature.title}
                                    </h3>

                                    <p className="text-[15px] sm:text-[16px] text-stone-gray leading-relaxed mb-6">
                                        {feature.description}
                                    </p>
                                </div>

                                {idx === 0 && (
                                    <div className="bg-cream-paper rounded-2xl p-5 border border-sandstone/50 space-y-3">
                                        <div className="flex justify-between items-center text-xs text-ink-black font-semibold">
                                            <span>Prediksi Regresi Linier Pagu</span>
                                            <span className="bg-pure-white px-3 py-1 rounded-[50px] border border-hairline-mist text-fresh-grass">
                                                Batas Pagu: Rp 14.500/kg
                                            </span>
                                        </div>
                                        <div className="space-y-2">
                                            <div className="w-full bg-hairline-mist h-2.5 rounded-full overflow-hidden">
                                                <div className="bg-fresh-grass h-full w-[78%] rounded-full transition-all duration-700" />
                                            </div>
                                            <div className="flex justify-between text-[11px] text-stone-gray">
                                                <span>Harga Historis BI 30 Hari: Rp 13.900</span>
                                                <span className="text-coral-pop font-bold">Mark-up Bebas: Rp 16.800</span>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {idx === 1 && (
                                    <div className="bg-cream-paper rounded-2xl p-4 border border-sandstone/50 space-y-2 text-xs">
                                        <div className="bg-pure-white p-3 rounded-xl border border-hairline-mist">
                                            <span className="font-bold text-[11px] text-stone-gray block">Vendor Mitra:</span>
                                            Penawaran awal diajukan pada nominal Rp 14.850/kg.
                                        </div>
                                        <div className="bg-fresh-grass/20 p-3 rounded-xl border border-fresh-grass/40 text-ink-black">
                                            <span className="font-bold text-[11px] text-stone-gray block">PAGORA Anchoring Bot:</span>
                                            Batas Harga Pagu sistem Rp 14.500/kg. Penawaran terkunci ke batas aman.
                                        </div>
                                    </div>
                                )}

                                {idx === 2 && (
                                    <div className="space-y-2">
                                        {['Dokumen PO Sistem Terkunci', 'Laporan Penerimaan Gudang (GRN)', 'Invoice Tagihan Vendor'].map((item, i) => (
                                            <div key={i} className="flex items-center justify-between bg-cream-paper px-4 py-3 rounded-xl border border-sandstone/50 text-xs">
                                                <span className="font-semibold text-ink-black">{item}</span>
                                                <span className="w-5 h-5 rounded-full bg-fresh-grass flex items-center justify-center font-bold text-ink-black text-[10px]">
                                                    ✓
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                {idx === 3 && (
                                    <div className="bg-cream-paper rounded-2xl p-4 border border-sandstone/50 space-y-2 text-xs">
                                        <div className="flex items-center gap-2 text-coral-pop font-bold">
                                            <AlertTriangle className="w-4 h-4" />
                                            <span>Deteksi Ketidaksesuaian Mutu</span>
                                        </div>
                                        <p className="text-stone-gray text-[12px]">
                                            Foto bukti verifikasi lab diunggah langsung di docking penerimaan. Dokumen seketika dibekukan otomatis dari proses pencairan dana.
                                        </p>
                                    </div>
                                )}

                                {idx === 4 && (
                                    <div className="bg-cream-paper rounded-2xl p-4 border border-sandstone/50 space-y-3 text-xs">
                                        <div className="flex items-center justify-between font-semibold">
                                            <span className="text-ink-black">Status Armada Truk #B-9142-PGA</span>
                                            <span className="text-fresh-grass">Dalam Perjalanan</span>
                                        </div>
                                        <div className="bg-pure-white p-3 rounded-xl border border-hairline-mist flex items-center justify-between">
                                            <span className="text-stone-gray">Estimasi Waktu Tiba (ETA):</span>
                                            <span className="font-bold text-ink-black">14.30 WIB (Tepat Waktu)</span>
                                        </div>
                                    </div>
                                )}

                                <div className="mt-6 pt-4 border-t border-sandstone/30 flex items-center justify-between text-xs text-stone-gray">
                                    <div className="flex flex-wrap gap-2">
                                        {feature.highlights.map((h, i) => (
                                            <span key={i} className="bg-cream-paper px-3 py-1.5 rounded-lg text-ink-black font-medium text-[11px]">
                                                {h}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
