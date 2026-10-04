import React, { useState, useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import Lenis from 'lenis';
import { 
    ChevronRight, 
    ShieldCheck, 
    AlertTriangle, 
    ArrowRight, 
    Check 
} from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Card, CardTitle, CardDescription } from '@/Components/ui/card';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';
import StackedFeaturesSection from '@/Components/StackedFeaturesSection';
import LoadingScreen from '@/Components/LoadingScreen';
import landingData from '@/data/landingData.json';

gsap.registerPlugin(ScrollTrigger);

export default function Home() {
    const { problems, features, workflowSteps, roleBenefits } = landingData;
    const [activeRoleIndex, setActiveRoleIndex] = useState(0);
    const [monthlySpend, setMonthlySpend] = useState(1500);
    const [isLoading, setIsLoading] = useState(true);
    const lenisRef = useRef(null);
    const headlineRef = useRef(null);
    const descRef = useRef(null);

    const estimatedSavings = Math.round(monthlySpend * 0.118);
    const manHoursSaved = Math.round(monthlySpend * 0.09);

    useEffect(() => {
        const lenis = new Lenis({
            lerp: 0.1,
            smoothWheel: true,
        });
        lenisRef.current = lenis;

        // Prevent smooth scrolling while loading screen is covering the viewport
        if (isLoading) {
            lenis.stop();
        }

        lenis.on('scroll', ScrollTrigger.update);

        const updateTicker = (time) => {
            lenis.raf(time * 1000);
        };

        gsap.ticker.add(updateTicker);
        gsap.ticker.lagSmoothing(0);

        const ctx = gsap.context(() => {
            gsap.to('.hero-display-headline', {
                yPercent: -20,
                ease: 'none',
                scrollTrigger: {
                    trigger: '.hero-section',
                    start: 'top top',
                    end: 'bottom top',
                    scrub: true,
                },
            });

            gsap.to('.hero-mock-panel', {
                yPercent: -12,
                ease: 'none',
                scrollTrigger: {
                    trigger: '.hero-section',
                    start: 'top top',
                    end: 'bottom top',
                    scrub: true,
                },
            });
        });

        return () => {
            ctx.revert();
            gsap.ticker.remove(updateTicker);
            lenis.destroy();
            ScrollTrigger.getAll().forEach((t) => t.kill());
        };
    }, []);

    // Triggered precisely when the loading screen finishes drawing the logo and begins sliding UP
    const handleStartReveal = () => {
        if (lenisRef.current) {
            lenisRef.current.start();
        }

        const prefersReducedMotion = typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReducedMotion) {
            return;
        }

        const headlineEl = headlineRef.current;
        const descEl = descRef.current;
        if (!headlineEl || !descEl) {
            return;
        }

        // GSAP Timeline for Hero text entrance: Heading first, followed by Paragraph <p>
        const heroTl = gsap.timeline({
            defaults: {
                ease: 'power3.out',
            },
        });

        // 1. Heading slides down smoothly from a subtle offset above
        heroTl.fromTo(
            headlineEl,
            {
                opacity: 0,
                y: -32,
            },
            {
                opacity: 1,
                y: 0,
                duration: 0.85,
                clearProps: 'opacity,y',
            },
            0.12 // Starts as the cream loading screen begins sliding up
        );

        // 2. Paragraph <p> follows with a slight delay/stagger
        heroTl.fromTo(
            descEl,
            {
                opacity: 0,
                y: -20,
            },
            {
                opacity: 1,
                y: 0,
                duration: 0.8,
                clearProps: 'opacity,y',
            },
            0.28 // Staggered slightly after heading
        );
    };

    const handleLoadingComplete = () => {
        setIsLoading(false);
        ScrollTrigger.refresh();
    };

    return (
        <div className="min-h-screen bg-cream-paper text-ink-black font-sans selection:bg-fresh-grass selection:text-ink-black relative">
            <Head>
                <title>PAGORA — Sistem E-Procurement B2B Berbasis Harga Referensi & 3-Way Matching</title>
                <meta 
                    name="description" 
                    content="Platform portal e-procurement terintegrasi untuk industri manufaktur dan pemasok bahan baku. Bebas mark-up, transparan, dan terkendali dengan rekomendasi Harga Pagu API Bank Indonesia." 
                />
            </Head>

            {/* Fullscreen Loading Screen with GSAP SVG Draw/Reveal animation */}
            {isLoading && (
                <LoadingScreen 
                    onStartReveal={handleStartReveal} 
                    onComplete={handleLoadingComplete} 
                />
            )}

            <Navbar />

            <section className="hero-section relative pt-8 sm:pt-14 pb-16 sm:pb-24 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto">
                <div className="text-center max-w-4xl mx-auto space-y-6">
                    <h1 
                        ref={headlineRef}
                        className="hero-display-headline text-[38px] sm:text-[62px] lg:text-[82px] font-medium text-ink-black tracking-tight leading-[1.02] will-change-transform"
                    >
                        Ekosistem Pengadaan Bahan Baku B2B: <br className="hidden sm:inline" />
                        <span className="relative inline-block">
                            Bebas Mark-Up,
                            <svg className="absolute -bottom-2 left-0 w-full h-3 text-fresh-grass" viewBox="0 0 200 12" fill="none" preserveAspectRatio="none">
                                <path d="M2 9C50 2 150 2 198 9" stroke="currentColor" strokeWidth="4" strokeLinecap="round" />
                            </svg>
                        </span>{' '}
                        Transparan, dan Terkendali.
                    </h1>

                    <p 
                        ref={descRef}
                        className="hero-anim-desc text-[17px] sm:text-subheading text-stone-gray leading-relaxed max-w-3xl mx-auto font-normal"
                    >
                        Platform portal terintegrasi untuk industri manufaktur dan pemasok bahan baku. Lindungi anggaran pengadaan dengan rekomendasi <strong className="text-ink-black font-medium">Harga Pagu cerdas</strong> dan otomasi validasi dokumen <strong className="text-ink-black font-medium">3-Way Matching</strong>.
                    </p>

                    <div className="hero-anim-cta flex flex-wrap items-center justify-center gap-3 pt-2">
                        <Link
                            href="/register-vendor"
                            className="px-6 py-3.5 rounded-[50px] bg-ink-black text-pure-white text-sm font-medium hover:bg-pure-ink active:scale-95 transition-all shadow-sm inline-flex items-center gap-2"
                        >
                            <span>Mulai Sebagai Vendor Mitra</span>
                            <ArrowRight className="w-4 h-4 text-fresh-grass" />
                        </Link>
                        <a
                            href="#calculator"
                            className="px-6 py-3.5 rounded-[50px] bg-pure-white border border-hairline-mist text-ink-black text-sm font-medium hover:bg-cream-paper active:scale-95 transition-all shadow-xs"
                        >
                            Hitung Estimasi Penghematan
                        </a>
                    </div>
                </div>

                <div className="hero-mock-panel mt-14 max-w-5xl mx-auto will-change-transform">
                    <div className="bg-pure-white rounded-[44px] sm:rounded-illustration border border-hairline-mist p-6 sm:p-10 relative overflow-hidden">
                        <div className="flex items-center justify-between mb-8 pb-6 border-b border-cream-paper">
                            <div className="flex items-center gap-3">
                                <span className="w-3.5 h-3.5 rounded-full bg-fresh-grass" />
                                <span className="w-3.5 h-3.5 rounded-full bg-sky-pop" />
                                <span className="w-3.5 h-3.5 rounded-full bg-coral-pop" />
                                <span className="w-3.5 h-3.5 rounded-full bg-sunshine-pop" />
                                <span className="text-xs font-semibold text-stone-gray uppercase tracking-wider ml-2">
                                    Simulasi Ruang Kerja PAGORA Enterprise
                                </span>
                            </div>
                            <div className="hidden sm:inline-flex items-center gap-2 px-3 py-1 rounded-[50px] bg-cream-paper text-xs font-medium text-ink-black">
                                <span className="w-2 h-2 rounded-full bg-fresh-grass" />
                                API Bank Indonesia Terhubung
                            </div>
                        </div>

                        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                            <div className="lg:col-span-7 bg-cream-paper rounded-[36px] sm:rounded-[44px] p-6 sm:p-7 border border-sandstone">
                                <div className="flex items-center justify-between mb-4">
                                    <Badge variant="white" pill dotColor="bg-fresh-grass">
                                        PO #PAG-2026-094
                                    </Badge>
                                    <span className="text-xs text-stone-gray">Bahan Baku: CPO & Olein Industri</span>
                                </div>

                                <div className="space-y-3">
                                    <div className="bg-pure-white rounded-3xl p-4 border border-hairline-mist/60">
                                        <div className="flex justify-between items-center text-xs text-stone-gray mb-1">
                                            <span>Rata-Rata Penawaran Pasar Bebas</span>
                                            <span className="text-coral-pop font-semibold">Tinggi / Mark-up Risk</span>
                                        </div>
                                        <div className="text-xl sm:text-2xl font-bold text-ink-black">
                                            Rp 16.450 <span className="text-xs font-normal text-stone-gray">/ kg</span>
                                        </div>
                                    </div>

                                    <div className="bg-pure-white rounded-3xl p-4 border-2 border-fresh-grass">
                                        <div className="flex justify-between items-center text-xs text-stone-gray mb-1">
                                            <span className="font-semibold text-ink-black flex items-center gap-1.5">
                                                <span className="w-2 h-2 rounded-full bg-fresh-grass" />
                                                Rekomendasi Harga Pagu PAGORA
                                            </span>
                                            <span className="text-fresh-grass font-bold">Hemat 11.2%</span>
                                        </div>
                                        <div className="text-2xl sm:text-3xl font-extrabold text-ink-black flex items-baseline gap-2">
                                            Rp 14.600 <span className="text-xs font-normal text-stone-gray">/ kg (Regresi Linier BI)</span>
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-4 pt-4 border-t border-sandstone flex items-center justify-between text-xs text-stone-gray">
                                    <span>Estimasi Penghematan Volume 100 Ton:</span>
                                    <span className="font-bold text-ink-black text-sm bg-pure-white px-3 py-1 rounded-[50px] border border-hairline-mist">
                                        Rp 185.000.000,-
                                    </span>
                                </div>
                            </div>

                            <div className="lg:col-span-5 space-y-4">
                                <div className="bg-cream-paper rounded-[36px] p-5 border border-sandstone">
                                    <div className="flex items-center justify-between mb-3">
                                        <span className="text-xs font-semibold text-ink-black uppercase tracking-wide">
                                            3-Way Match Verification
                                        </span>
                                        <span className="text-[11px] font-bold text-fresh-grass bg-pure-white px-2.5 py-0.5 rounded-[50px] border border-hairline-mist">
                                            MATCHED ✓
                                        </span>
                                    </div>
                                    <div className="space-y-2 text-xs">
                                        <div className="flex items-center justify-between bg-pure-white px-3.5 py-2 rounded-[50px] border border-hairline-mist/60">
                                            <span className="text-stone-gray">1. Dokumen PO Terkunci</span>
                                            <span className="font-semibold text-ink-black">100.000 kg @ Rp 14.600</span>
                                        </div>
                                        <div className="flex items-center justify-between bg-pure-white px-3.5 py-2 rounded-[50px] border border-hairline-mist/60">
                                            <span className="text-stone-gray">2. Laporan QC Gudang (GRN)</span>
                                            <span className="font-semibold text-ink-black text-xs">100.000 kg (Foto Terlampir)</span>
                                        </div>
                                        <div className="flex items-center justify-between bg-pure-white px-3.5 py-2 rounded-[50px] border border-hairline-mist/60">
                                            <span className="text-stone-gray">3. Invoice Pajak Vendor</span>
                                            <span className="font-semibold text-ink-black">Rp 1.460.000.000,-</span>
                                        </div>
                                    </div>
                                </div>

                                <div className="bg-pure-white rounded-4xl p-4 border border-hairline-mist flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-full bg-fresh-grass flex items-center justify-center shrink-0">
                                        <ShieldCheck className="w-5 h-5 text-ink-black" />
                                    </div>
                                    <div>
                                        <div className="text-xs font-bold text-ink-black">
                                            Dispute Freezing Aktif
                                        </div>
                                        <div className="text-[11px] text-stone-gray">
                                            Jika kuantitas/mutu menyimpang, pencairan tagihan seketika dibekukan.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="problems" className="w-full bg-fresh-grass text-ink-black py-20 sm:py-28 px-4 sm:px-6 lg:px-8 border-y-2 border-ink-black">
                <div className="max-w-6xl mx-auto">
                    <motion.div 
                        initial={{ opacity: 0, y: 20 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        viewport={{ once: true }}
                        transition={{ duration: 0.5 }}
                        className="text-center max-w-3xl mx-auto mb-16 space-y-4"
                    >
                        <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-[50px] bg-pure-white border border-ink-black text-xs font-semibold text-ink-black shadow-sm">
                            <span className="w-2.5 h-2.5 rounded-full bg-coral-pop" />
                            <span>Anatomi Masalah Pengadaan Konvensional</span>
                        </div>
                        <h2 className="text-[32px] sm:text-[48px] font-medium text-ink-black tracking-tight leading-[1.1]">
                            Celah Operasional yang Menguras Anggaran Manufaktur
                        </h2>
                        <p className="text-[16px] sm:text-body-lg text-ink-black/85 leading-relaxed font-normal">
                            Pengadaan bahan baku secara manual dan berbasis kepercayaan sepihak membuka ruang mark-up, ketidaksesuaian mutu, serta kerugian finansial yang tak terdeteksi.
                        </p>
                    </motion.div>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-5 lg:gap-8 items-stretch">
                        {problems.map((problem, idx) => (
                            <motion.div
                                key={problem.id}
                                initial={{ opacity: 0, y: 24 }}
                                whileInView={{ opacity: 1, y: 0 }}
                                viewport={{ once: true }}
                                transition={{ duration: 0.45, delay: idx * 0.12 }}
                                whileHover={{ y: -6 }}
                                className="h-full flex flex-col"
                            >
                                <Card className="h-full flex-1 flex flex-col transition-all duration-300 relative group bg-pure-white rounded-[36px] sm:rounded-[44px] xl:rounded-[50px] border-2 border-ink-black p-6 lg:p-7 xl:p-8 shadow-sm">
                                    <div className="flex flex-col">
                                        <div className="flex items-center justify-between mb-4 sm:mb-5">
                                            <span className="text-3xl font-extrabold text-ink-black/30 group-hover:text-ink-black transition-colors">
                                                {problem.number}
                                            </span>
                                            <span className="px-3 py-1 rounded-[50px] text-xs font-semibold bg-cream-paper text-ink-black border border-ink-black/30">
                                                {problem.tag}
                                            </span>
                                        </div>

                                        <CardTitle className="mb-3 sm:mb-4 text-subheading sm:text-[22px] lg:text-[24px] text-ink-black font-medium leading-[1.2] min-h-12 sm:min-h-13.5 lg:min-h-14.5 flex items-start">
                                            {problem.title}
                                        </CardTitle>

                                        <CardDescription className="text-stone-gray leading-relaxed text-[14px] sm:text-body-sm min-h-16.5 sm:min-h-20 lg:min-h-21.5 xl:min-h-18">
                                            {problem.description}
                                        </CardDescription>
                                    </div>

                                    <div className="mt-auto pt-5 sm:pt-6 border-t border-cream-paper space-y-3">
                                        <div className="text-[10px] sm:text-[11px] md:text-[9.5px] lg:text-[11px] xl:text-xs font-bold uppercase tracking-tight lg:tracking-normal xl:tracking-wide text-coral-pop flex items-center gap-1.5 h-6 whitespace-nowrap overflow-hidden">
                                            <span className="w-2 h-2 rounded-full bg-coral-pop shrink-0" />
                                            <span className="truncate">{problem.impact}</span>
                                        </div>
                                        <div className="text-xs italic text-stone-gray bg-cream-paper p-3 sm:p-3.5 rounded-[20px] border border-sandstone min-h-18.5 sm:min-h-20.5 lg:min-h-19.5 flex items-center">
                                            <p className="leading-relaxed">
                                                {problem.quote}
                                            </p>
                                        </div>
                                    </div>
                                </Card>
                            </motion.div>
                        ))}
                    </div>
                </div>
            </section>

            <StackedFeaturesSection features={features} />

            <section id="workflow" className="w-full bg-fresh-grass text-ink-black py-20 sm:py-28 px-4 sm:px-6 lg:px-8 border-y-2 border-ink-black relative">
                <div className="max-w-7xl mx-auto">
                    <div className="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-16 items-start">
                        <div className="lg:col-span-5 lg:sticky lg:top-28 lg:self-start space-y-6">
                            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-[50px] bg-pure-white border border-ink-black text-xs font-semibold text-ink-black shadow-sm">
                                <span className="w-2.5 h-2.5 rounded-full bg-ink-black" />
                                <span>Langkah Transaksi Sistematis</span>
                            </div>

                            <h2 className="text-[34px] sm:text-[50px] font-medium text-ink-black tracking-tight leading-[1.08]">
                                Alur Kerja Transparan: Dari Analisis hingga Bayar
                            </h2>

                            <p className="text-[16px] sm:text-body-lg text-ink-black/85 leading-relaxed font-normal">
                                Proses empat langkah yang memastikan efisiensi waktu, kepatuhan harga, dan keamanan rekonsiliasi pembayaran tanpa celah sengketa dokumen.
                            </p>

                            <div className="bg-pure-white rounded-4xl border-2 border-ink-black p-6 shadow-sm space-y-4">
                                <div className="text-xs uppercase font-mono font-bold tracking-wider text-stone-gray">
                                    Tahapan Eksekusi Pengadaan
                                </div>
                                <div className="space-y-3">
                                    {workflowSteps.map((step, idx) => (
                                        <div key={idx} className="flex items-center gap-3 text-sm">
                                            <span className="w-7 h-7 rounded-full bg-cream-paper border border-ink-black font-bold text-xs flex items-center justify-center text-ink-black shrink-0">
                                                0{idx + 1}
                                            </span>
                                            <span className="font-medium text-ink-black text-xs sm:text-sm truncate">
                                                {step.title}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                                <div className="pt-2 border-t border-cream-paper flex items-center justify-between text-xs text-stone-gray">
                                    <span className="flex items-center gap-1.5 font-semibold text-ink-black">
                                        <Check className="w-4 h-4 text-fresh-grass" />
                                        100% Digital & Terverifikasi
                                    </span>
                                </div>
                            </div>

                            <div className="pt-2">
                                <a
                                    href="#roles"
                                    className="inline-flex items-center gap-2 px-6 py-3 rounded-[50px] bg-ink-black text-pure-white hover:bg-pure-ink text-[14px] font-medium transition-colors shadow-sm"
                                >
                                    <span>Lihat Hubungan Antar-Peran</span>
                                    <ArrowRight className="w-4 h-4" />
                                </a>
                            </div>
                        </div>

                        <div className="lg:col-span-7 space-y-6 sm:space-y-8">
                            {workflowSteps.map((item, index) => (
                                <div 
                                    key={index}
                                    className="bg-pure-white rounded-[40px] sm:rounded-[50px] border-2 border-ink-black p-7 sm:p-9 shadow-sm transition-all hover:-translate-y-1"
                                >
                                    <div className="flex items-center justify-between mb-6">
                                        <div className="flex items-center gap-3">
                                            <div className={`w-12 h-12 rounded-[50px] ${item.pillBg} text-ink-black font-bold text-lg flex items-center justify-center border-2 border-ink-black`}>
                                                {item.step}
                                            </div>
                                            <span className="text-xs font-bold text-ink-black bg-cream-paper border border-ink-black/30 px-3.5 py-1.5 rounded-[50px]">
                                                {item.badge}
                                            </span>
                                        </div>
                                        <span className="text-xs font-mono font-bold text-stone-gray">
                                            Fase 0{index + 1} / 04
                                        </span>
                                    </div>

                                    <h3 className="text-[22px] sm:text-[26px] font-medium text-ink-black tracking-tight mb-3 leading-snug">
                                        {item.title}
                                    </h3>

                                    <p className="text-body-sm sm:text-[16px] text-stone-gray leading-relaxed mb-6">
                                        {item.desc}
                                    </p>

                                    {index === 0 && (
                                        <div className="bg-cream-paper rounded-[28px] p-4 sm:p-5 border border-sandstone space-y-2 text-xs">
                                            <div className="flex justify-between items-center font-semibold text-ink-black">
                                                <span>Penetapan Batas Pagu API Bank Indonesia</span>
                                                <span className="text-fresh-grass bg-pure-white px-2.5 py-0.5 rounded-[50px] border border-hairline-mist">
                                                    Terkunci
                                                </span>
                                            </div>
                                            <p className="text-stone-gray">
                                                PO diterbitkan otomatis dengan acuan harga pasar terverifikasi, menutup peluang mark-up pengadaan.
                                            </p>
                                        </div>
                                    )}

                                    {index === 1 && (
                                        <div className="bg-cream-paper rounded-[28px] p-4 sm:p-5 border border-sandstone space-y-2 text-xs">
                                            <div className="flex justify-between items-center font-semibold text-ink-black">
                                                <span>Guided Bidding & Self-Reporting Logistics</span>
                                                <span className="text-sky-pop bg-pure-white px-2.5 py-0.5 rounded-[50px] border border-hairline-mist">
                                                    Portal Vendor
                                                </span>
                                            </div>
                                            <p className="text-stone-gray">
                                                Vendor mengonfirmasi harga dan menjadwalkan armada mandiri dengan estimasi waktu tiba transparan.
                                            </p>
                                        </div>
                                    )}

                                    {index === 2 && (
                                        <div className="bg-cream-paper rounded-[28px] p-4 sm:p-5 border border-sandstone space-y-2 text-xs">
                                            <div className="flex justify-between items-center font-semibold text-ink-black">
                                                <span>Inspeksi Docking & Foto Mutu Fisik</span>
                                                <span className="text-coral-pop bg-pure-white px-2.5 py-0.5 rounded-[50px] border border-hairline-mist">
                                                    QC Gudang
                                                </span>
                                            </div>
                                            <p className="text-stone-gray">
                                                GRN digital diterbitkan hanya jika kondisi fisik sesuai; sengketa seketika membekukan dokumen pencairan dana.
                                            </p>
                                        </div>
                                    )}

                                    {index === 3 && (
                                        <div className="bg-cream-paper rounded-[28px] p-4 sm:p-5 border border-sandstone space-y-2 text-xs">
                                            <div className="flex justify-between items-center font-semibold text-ink-black">
                                                <span>Otomasi 3-Way Match PO + GRN + Invoice</span>
                                                <span className="text-fresh-grass bg-pure-white px-2.5 py-0.5 rounded-[50px] border border-hairline-mist">
                                                    MATCHED ✓
                                                </span>
                                            </div>
                                            <p className="text-stone-gray">
                                                Sistem memverifikasi keselarasan kuantitas, harga, dan mutu sebelum approval pelunasan dibuka aman.
                                            </p>
                                        </div>
                                    )}

                                    <div className="mt-6 pt-4 border-t border-cream-paper flex items-center justify-between text-xs text-stone-gray">
                                        <span>Siklus Transaksi Terkendali</span>
                                        <ArrowRight className="w-4 h-4 text-ink-black" />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            <section id="roles" className="py-20 sm:py-28 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto border-t border-sandstone">
                <motion.div 
                    initial={{ opacity: 0, y: 20 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    viewport={{ once: true }}
                    transition={{ duration: 0.5 }}
                    className="text-center max-w-3xl mx-auto mb-14 space-y-4"
                >
                    <Badge variant="coral" pill dotColor="bg-coral-pop">
                        Multi-Role Collaboration
                    </Badge>
                    <h2 className="text-[32px] sm:text-heading font-medium text-ink-black tracking-tight leading-[1.1]">
                        Satu Platform, Nilai Manfaat Bagi Seluruh Peran
                    </h2>
                    <p className="text-[16px] sm:text-body-lg text-stone-gray leading-relaxed">
                        Dirancang untuk mengikis sekat komunikasi dan menyatukan kepentingan bisnis dalam rantai pasok pengadaan manufaktur.
                    </p>
                </motion.div>

                <div className="flex flex-wrap items-center justify-center gap-2 sm:gap-3 mb-10">
                    {roleBenefits.map((roleItem, index) => (
                        <button
                            key={index}
                            type="button"
                            onClick={() => setActiveRoleIndex(index)}
                            className={`px-5 py-3 rounded-[50px] text-[14px] sm:text-body-sm font-medium transition-all cursor-pointer ${
                                activeRoleIndex === index
                                    ? 'bg-ink-black text-pure-white shadow-sm'
                                    : 'bg-pure-white text-ink-black border border-hairline-mist hover:bg-cream-paper'
                            }`}
                        >
                            {roleItem.role}
                        </button>
                    ))}
                </div>

                <div className="bg-pure-white rounded-[44px] sm:rounded-[56px] border border-hairline-mist p-8 sm:p-12 transition-all overflow-hidden">
                    <AnimatePresence mode="wait">
                        <motion.div 
                            key={activeRoleIndex}
                            initial={{ opacity: 0, y: 12 }}
                            animate={{ opacity: 1, y: 0 }}
                            exit={{ opacity: 0, y: -12 }}
                            transition={{ duration: 0.25, ease: "easeOut" }}
                            className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center"
                        >
                            <div className="lg:col-span-7 space-y-6">
                                <div className="inline-block px-3 py-1 rounded-[50px] bg-cream-paper text-xs font-semibold text-ink-black border border-hairline-mist">
                                    Fokus Peran: {roleBenefits[activeRoleIndex].role}
                                </div>
                                <h3 className="text-[28px] sm:text-[38px] font-medium text-ink-black tracking-tight leading-heading">
                                    {roleBenefits[activeRoleIndex].title}
                                </h3>
                                <p className="text-[17px] text-stone-gray leading-relaxed">
                                    {roleBenefits[activeRoleIndex].tagline}
                                </p>

                                <ul className="space-y-3 pt-2">
                                    {roleBenefits[activeRoleIndex].bulletPoints.map((point, i) => (
                                        <li key={i} className="flex items-start gap-3 text-body-sm text-ink-black">
                                            <div className="w-5 h-5 rounded-full bg-fresh-grass flex items-center justify-center shrink-0 mt-0.5 text-ink-black">
                                                <Check className="w-3.5 h-3.5" />
                                            </div>
                                            <span>{point}</span>
                                        </li>
                                    ))}
                                </ul>

                                <div className="pt-4 flex flex-wrap gap-4">
                                    <Link
                                        href={activeRoleIndex === 1 ? "/register-vendor" : "/login"}
                                        className="inline-flex items-center gap-2 px-6 py-3 rounded-[50px] bg-coral-pop text-pure-white text-body-sm font-medium hover:opacity-90 transition-opacity"
                                    >
                                        <span>{roleBenefits[activeRoleIndex].actionText}</span>
                                        <span className="w-2 h-2 rounded-full bg-pure-white" />
                                    </Link>
                                    <a
                                        href="#workflow"
                                        className="inline-flex items-center gap-2 px-6 py-3 rounded-[50px] bg-cream-paper text-ink-black text-body-sm font-medium border border-hairline-mist hover:bg-sandstone transition-colors"
                                    >
                                        Lihat Hubungan Antar-Peran
                                    </a>
                                </div>
                            </div>

                            <div className="lg:col-span-5 bg-cream-paper rounded-[40px] p-8 border border-sandstone flex flex-col justify-between text-center space-y-6">
                                <span className="text-xs uppercase tracking-wider font-semibold text-stone-gray">
                                    Dampak Terukur Penggunaan PAGORA
                                </span>
                                <div>
                                    <div className="text-[56px] sm:text-[68px] font-extrabold text-ink-black tracking-tight leading-none">
                                        {roleBenefits[activeRoleIndex].metric}
                                    </div>
                                    <div className="text-sm font-semibold text-ink-black mt-2">
                                        {roleBenefits[activeRoleIndex].metricLabel}
                                    </div>
                                </div>
                                <div className="text-xs text-stone-gray bg-pure-white p-4 rounded-3xl border border-hairline-mist/70">
                                    Berdasarkan benchmarking implementasi pengadaan bahan baku manufaktur berulang.
                                </div>
                            </div>
                        </motion.div>
                    </AnimatePresence>
                </div>
            </section>

            <section id="calculator" className="py-20 sm:py-28 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto border-t border-sandstone">
                <motion.div 
                    initial={{ opacity: 0, scale: 0.98 }}
                    whileInView={{ opacity: 1, scale: 1 }}
                    viewport={{ once: true }}
                    transition={{ duration: 0.55 }}
                    className="bg-ink-black text-pure-white rounded-[44px] sm:rounded-illustration p-8 sm:p-14 relative overflow-hidden"
                >
                    <div className="flex items-center gap-2 mb-8">
                        <span className="w-3 h-3 rounded-full bg-fresh-grass" />
                        <span className="w-3 h-3 rounded-full bg-sky-pop" />
                        <span className="w-3 h-3 rounded-full bg-coral-pop" />
                        <span className="text-xs font-mono uppercase text-stone-gray ml-2">
                            Simulasi Penghematan Anggaran Pengadaan
                        </span>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">
                        <div className="lg:col-span-6 space-y-6">
                            <h2 className="text-[32px] sm:text-[46px] font-medium tracking-tight leading-[1.1]">
                                Hitung Potensi Efisiensi Anggaran Manufaktur Anda
                            </h2>
                            <p className="text-hairline-mist text-[16px] leading-relaxed">
                                Geser nilai belanja bulanan bahan baku pabrik Anda untuk melihat estimasi nominal mark-up yang dapat dihemat melalui penetapan Harga Pagu cerdas.
                            </p>

                            <div className="bg-white/10 p-6 rounded-4xl border border-white/15 space-y-4">
                                <div className="flex justify-between items-center">
                                    <label className="text-xs font-semibold uppercase tracking-wider text-hairline-mist">
                                        Anggaran Belanja Bahan Baku / Bulan
                                    </label>
                                    <span className="text-xl sm:text-2xl font-bold text-fresh-grass">
                                        Rp {monthlySpend.toLocaleString('id-ID')} Juta
                                    </span>
                                </div>
                                <input
                                    type="range"
                                    min="200"
                                    max="10000"
                                    step="100"
                                    value={monthlySpend}
                                    onChange={(e) => setMonthlySpend(Number(e.target.value))}
                                    className="w-full accent-fresh-grass cursor-pointer h-2 bg-white/20 rounded-lg"
                                />
                                <div className="flex justify-between text-[11px] text-stone-gray">
                                    <span>Rp 200 Juta</span>
                                    <span>Rp 5 Milyar</span>
                                    <span>Rp 10 Milyar</span>
                                </div>
                            </div>
                        </div>

                        <div className="lg:col-span-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="bg-pure-white text-ink-black p-6 rounded-4xl border border-white/20">
                                <span className="text-xs font-semibold text-stone-gray uppercase block mb-1">
                                    Estimasi Hemat Mark-Up (11.8%)
                                </span>
                                <div className="text-2xl sm:text-3xl font-extrabold text-ink-black">
                                    Rp {estimatedSavings.toLocaleString('id-ID')} Jt
                                </div>
                                <span className="text-xs text-stone-gray block mt-2">
                                    Perlindungan batas Pagu Bank Indonesia
                                </span>
                            </div>

                            <div className="bg-pure-white text-ink-black p-6 rounded-4xl border border-white/20">
                                <span className="text-xs font-semibold text-stone-gray uppercase block mb-1">
                                    Jam Rekonsiliasi Terpangkas
                                </span>
                                <div className="text-2xl sm:text-3xl font-extrabold text-ink-black">
                                    {manHoursSaved} Jam
                                </div>
                                <span className="text-xs text-stone-gray block mt-2">
                                    Otomasi 3-Way Matching tanpa manual
                                </span>
                            </div>

                            <div className="sm:col-span-2 bg-fresh-grass text-ink-black p-6 rounded-4xl flex items-center justify-between">
                                <div>
                                    <div className="text-xs font-bold uppercase tracking-wider">
                                        Kepatuhan Audit Dokumen
                                    </div>
                                    <div className="text-2xl sm:text-3xl font-extrabold">
                                        100% Terverifikasi
                                    </div>
                                </div>
                                <Link
                                    href="/login"
                                    className="px-6 py-3 rounded-[50px] bg-ink-black text-pure-white text-sm font-semibold hover:bg-pure-ink transition-colors"
                                >
                                    Uji Sekarang
                                </Link>
                            </div>
                        </div>
                    </div>
                </motion.div>
            </section>

            <section className="w-full bg-fresh-grass text-ink-black py-20 sm:py-28 px-4 sm:px-6 lg:px-8 border-t-2 border-ink-black relative overflow-hidden">
                <div className="max-w-4xl mx-auto text-center space-y-6">
                    <motion.div 
                        initial={{ opacity: 0, y: 20 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        viewport={{ once: true }}
                        transition={{ duration: 0.5 }}
                        className="space-y-6"
                    >
                        <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-[50px] bg-pure-white border border-ink-black text-xs font-semibold text-ink-black shadow-sm">
                            <span className="w-2.5 h-2.5 rounded-full bg-ink-black" />
                            <span>Transformasi Digital Pengadaan</span>
                        </div>

                        <h2 className="text-[34px] sm:text-[54px] font-medium text-ink-black tracking-tight leading-[1.08] max-w-3xl mx-auto">
                            Siap Mengamankan Anggaran Bahan Baku Manufaktur Anda?
                        </h2>

                        <p className="text-[17px] sm:text-[19px] text-ink-black/85 leading-relaxed max-w-2xl mx-auto font-normal">
                            Koneksikan pengadaan pabrik Anda dengan data harga obyektif Bank Indonesia dan hilangkan sengketa invoice selamanya.
                        </p>

                        <div className="pt-4 flex flex-col sm:flex-row items-center justify-center gap-4">
                            <Link
                                href="/login"
                                className="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-8 py-4 rounded-[50px] bg-ink-black hover:bg-pure-ink text-pure-white text-[16px] font-medium transition-all shadow-sm"
                            >
                                <span>Masuk Portal Pengadaan</span>
                                <span className="w-2.5 h-2.5 rounded-full bg-fresh-grass" />
                            </Link>

                            <Link
                                href="/register-vendor"
                                className="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-8 py-4 rounded-[50px] bg-pure-white hover:bg-cream-paper text-ink-black border border-ink-black text-[16px] font-medium transition-all shadow-sm"
                            >
                                <span>Daftar Menjadi Vendor Mitra</span>
                                <span className="w-2.5 h-2.5 rounded-full bg-coral-pop" />
                            </Link>
                        </div>

                        <div className="pt-6 flex flex-wrap items-center justify-center gap-4 sm:gap-6 text-xs text-ink-black font-medium">
                            <span className="flex items-center gap-1.5 bg-white/70 px-4 py-2 rounded-[50px] border border-ink-black/20">
                                <Check className="w-4 h-4 text-ink-black" />
                                Terintegrasi API Bank Indonesia
                            </span>
                            <span className="flex items-center gap-1.5 bg-white/70 px-4 py-2 rounded-[50px] border border-ink-black/20">
                                <Check className="w-4 h-4 text-ink-black" />
                                Audit Trail 3-Way Matching
                            </span>
                            <span className="flex items-center gap-1.5 bg-white/70 px-4 py-2 rounded-[50px] border border-ink-black/20">
                                <Check className="w-4 h-4 text-ink-black" />
                                Dispute Freezing Garansi
                            </span>
                        </div>
                    </motion.div>
                </div>
            </section>

            <Footer />
        </div>
    );
}