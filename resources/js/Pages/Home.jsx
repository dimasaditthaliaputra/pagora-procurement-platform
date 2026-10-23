import React, { useState, useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import Lenis from 'lenis';
import Snap from 'lenis/snap';
import {
    ArrowRight,
    Check,
    ChevronDown,
} from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { ContainerScroll } from '@/Components/ui/container-scroll-animation';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';
import ScrollPath from '@/Components/ScrollPath';
import StackedProblemsSection from '@/Components/StackedProblemsSection';
import StackedFeaturesSection from '@/Components/StackedFeaturesSection';
import SplitText from '@/Components/SplitText';
import LoadingScreen from '@/Components/LoadingScreen';
import landingData from '@/data/landingData.json';

gsap.registerPlugin(ScrollTrigger);

function FadeUp({ children, className = '', delay = 0, duration = 0.7, y = 30 }) {
    const ref = useRef(null);

    useEffect(() => {
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReducedMotion || !ref.current) return;

        gsap.set(ref.current, { opacity: 0, y });

        const ctx = gsap.context(() => {
            gsap.to(ref.current, {
                opacity: 1,
                y: 0,
                duration,
                delay,
                ease: 'power3.out',
                scrollTrigger: {
                    trigger: ref.current,
                    start: 'top 88%',
                    once: true,
                },
            });
        });

        return () => ctx.revert();
    }, [delay, duration, y]);

    return (
        <div ref={ref} className={className}>
            {children}
        </div>
    );
}

export default function Home() {
    const { problems, features, workflowSteps, roleBenefits } = landingData;
    const [activeRoleIndex, setActiveRoleIndex] = useState(0);
    const [monthlySpend, setMonthlySpend] = useState(1500);
    const [isLoading, setIsLoading] = useState(true);
    const lenisRef = useRef(null);
    const snapRef = useRef(null);
    const pageRef = useRef(null);
    const headlineRef = useRef(null);
    const descRef = useRef(null);
    const heroCtaRef = useRef(null);
    const scrollIndicatorRef = useRef(null);

    const estimatedSavings = Math.round(monthlySpend * 0.118);
    const manHoursSaved = Math.round(monthlySpend * 0.09);

    useEffect(() => {
        const lenis = new Lenis({
            lerp: 0.08,
            smoothWheel: true,
        });
        lenisRef.current = lenis;

        const snap = new Snap(lenis, {
            type: 'proximity',
            distanceThreshold: '50%',
            debounce: 200,
            duration: 0.9,
            easing: (t) => 1 - Math.pow(1 - t, 3),
        });
        snapRef.current = snap;

        if (isLoading) {
            lenis.stop();
            snap.stop();
        }

        // Snap points: the top of every section, the bottom edge of sections taller
        // than the viewport, and one point per viewport inside GSAP pin spacers
        // (so each pinned feature card lands exactly in place).
        let removeSnapPoints = [];
        const computeSnapPoints = () => {
            removeSnapPoints.forEach((remove) => remove());
            if (!pageRef.current) return;

            const vh = window.innerHeight;
            const sections = pageRef.current.querySelectorAll(
                ':scope > section, :scope > .pin-spacer > section, :scope > footer'
            );
            const points = [];

            sections.forEach((section) => {
                const spacer = section.parentElement.classList.contains('pin-spacer')
                    ? section.parentElement
                    : null;
                const box = spacer ?? section;
                const top = box.getBoundingClientRect().top + window.scrollY;
                const height = box.offsetHeight;

                points.push(top);

                if (spacer) {
                    const steps = Math.round((height - section.offsetHeight) / vh);
                    for (let i = 1; i <= steps; i++) points.push(top + i * vh);
                }

                if (height > vh) points.push(top + height - vh);
            });

            const values = points
                .map((value) => Math.round(Math.min(Math.max(value, 0), lenis.limit)))
                .sort((a, b) => a - b)
                .filter((value, i, arr) => i === 0 || value - arr[i - 1] > 4);

            removeSnapPoints = values.map((value) => snap.add(value));
        };

        computeSnapPoints();
        ScrollTrigger.addEventListener('refresh', computeSnapPoints);

        let resizeTimeout;
        const resizeObserver = new ResizeObserver(() => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(computeSnapPoints, 150);
        });
        if (pageRef.current) resizeObserver.observe(pageRef.current);

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
        });

        return () => {
            ctx.revert();
            gsap.ticker.remove(updateTicker);
            ScrollTrigger.removeEventListener('refresh', computeSnapPoints);
            resizeObserver.disconnect();
            clearTimeout(resizeTimeout);
            snap.destroy();
            lenis.destroy();
            ScrollTrigger.getAll().forEach((t) => t.kill());
        };
    }, []);

    const handleStartReveal = () => {
        if (lenisRef.current) {
            lenisRef.current.start();
        }
        if (snapRef.current) {
            snapRef.current.start();
        }

        const headlineEl = headlineRef.current;
        const descEl = descRef.current;
        const ctaEl = heroCtaRef.current;
        const scrollEl = scrollIndicatorRef.current;
        if (!headlineEl) return;

        const prefersReducedMotion = typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReducedMotion) {
            gsap.set([headlineEl, descEl, ctaEl, scrollEl].filter(Boolean), {
                opacity: 1,
                y: 0,
            });
            return;
        }

        const heroTl = gsap.timeline({
            defaults: { ease: 'power3.out' },
        });

        heroTl.fromTo(
            headlineEl,
            { opacity: 0, y: -30 },
            { opacity: 1, y: 0, duration: 1, clearProps: 'transform' },
            0.1
        );

        if (descEl) {
            heroTl.fromTo(
                descEl,
                { opacity: 0, y: -20 },
                { opacity: 1, y: 0, duration: 0.85, clearProps: 'transform' },
                0.35
            );
        }

        if (ctaEl) {
            heroTl.fromTo(
                ctaEl,
                { opacity: 0, y: 20 },
                { opacity: 1, y: 0, duration: 0.75, clearProps: 'transform' },
                0.55
            );
        }

        if (scrollEl) {
            heroTl.fromTo(
                scrollEl,
                { opacity: 0 },
                { opacity: 1, duration: 0.6 },
                0.75
            );
        }
    };

    const handleLoadingComplete = () => {
        setIsLoading(false);
        const elements = [headlineRef.current, descRef.current, heroCtaRef.current, scrollIndicatorRef.current].filter(Boolean);
        gsap.set(elements, { opacity: 1 });
        ScrollTrigger.refresh();
    };

    return (
        <div ref={pageRef} className="landing-page min-h-screen bg-cream-paper text-ink-black font-inter selection:bg-fresh-grass/30 selection:text-ink-black relative">
            <Head>
                <title>PAGORA — Sistem E-Procurement B2B Berbasis Harga Referensi & 3-Way Matching</title>
                <meta
                    name="description"
                    content="Platform portal e-procurement terintegrasi untuk industri manufaktur dan pemasok bahan baku. Bebas mark-up, transparan, dan terkendali dengan rekomendasi Harga Pagu API Bank Indonesia."
                />
            </Head>

            {isLoading && (
                <LoadingScreen
                    onStartReveal={handleStartReveal}
                    onComplete={handleLoadingComplete}
                />
            )}

            <ScrollPath />

            <Navbar />

            <section className="hero-section bg-fresh-grass relative flex flex-col items-center justify-center h-screen px-4 sm:px-6 lg:px-8 bg-cream-paper">
                <div className="text-center max-w-5xl mx-auto" style={{ paddingTop: '6vh', paddingBottom: '6vh' }}>
                    <h1
                        ref={headlineRef}
                        className="hero-display-headline text-display font-semibold text-ink-black tracking-display leading-display will-change-transform"
                    >
                        Ekosistem Pengadaan{' '}
                        <span className="relative inline-block">
                            Bebas Mark-Up
                            <svg className="absolute -bottom-1 sm:-bottom-2 left-0 w-full h-3 sm:h-4 text-amber-300" viewBox="0 0 200 12" fill="none" preserveAspectRatio="none">
                                <motion.path
                                    d="M2 9C50 2 150 2 198 9"
                                    stroke="currentColor"
                                    strokeWidth="4"
                                    strokeLinecap="round"
                                    initial={{ pathLength: 0, opacity: 1 }}
                                    animate={{ pathLength: 1, opacity: 1 }}
                                    transition={{
                                        duration: 1.2,
                                        delay: 0.6,
                                        ease: "easeOut"
                                    }}
                                />
                            </svg>
                        </span>
                    </h1>

                    <p
                        ref={descRef}
                        className="hero-subtitle text-[16px] font-medium text-ink-black leading-body-lg max-w-2xl mx-auto mt-6 sm:mt-8 will-change-transform"
                    >
                        Platform B2B terintegrasi dengan Harga Pagu referensi Bank Indonesia dan otomasi 3-Way Matching untuk transparansi pengadaan bahan baku manufaktur.
                    </p>
                </div>

                <div
                    ref={scrollIndicatorRef}
                    className="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2 text-ink-black"
                >
                    <span className="text-micro uppercase tracking-widest font-medium">Scroll</span>
                    <ChevronDown className="w-4 h-4 animate-bounce" />
                </div>
            </section>

            {/* ═══════ PRODUCT PREVIEW — Scroll Animation ═══════ */}
            <section id="preview" className="flex flex-col overflow-hidden bg-cream-paper">
                <ContainerScroll
                    titleComponent={
                        <h2 className="text-3xl font-semibold text-ink-black md:text-4xl">
                            Pantau seluruh pengadaan dalam <br />
                            <span className="mt-1 text-4xl font-bold leading-none md:text-[6rem]">
                                Satu Dasbor
                            </span>
                        </h2>
                    }
                >
                    <img
                        src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1400&h=720&q=80"
                        alt="Dasbor analitik pengadaan PAGORA"
                        height={720}
                        width={1400}
                        className="mx-auto h-full w-full rounded-2xl object-cover object-left-top"
                        draggable={false}
                        loading="lazy"
                    />
                </ContainerScroll>
            </section>

            {/* ═══════ PROBLEMS — Stacked Cards ═══════ */}
            <StackedProblemsSection problems={problems} />

            {/* ═══════ FEATURES — Pinned Stack ═══════ */}
            <StackedFeaturesSection features={features} />

            {/* ═══════ WORKFLOW ═══════ */}
            <section
                id="workflow"
                className="w-full relative bg-pure-white border-b border-hairline-mist/50"
                style={{ paddingTop: 'var(--section-py)', paddingBottom: 'var(--section-py)' }}
            >
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-20 items-start">
                        {/* Left sticky panel */}
                        <div className="lg:col-span-5 lg:sticky lg:top-28 lg:self-start space-y-6">
                            <FadeUp>
                                <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-[50px] bg-cream-paper border border-hairline-mist text-xs font-semibold text-ink-black shadow-sm">
                                    <span className="w-2.5 h-2.5 rounded-full bg-ink-black" />
                                    <span>Langkah Transaksi Sistematis</span>
                                </div>
                            </FadeUp>

                            <SplitText
                                as="h2"
                                className="text-heading-xl font-bold text-ink-black tracking-heading-xl leading-heading-xl"
                            >
                                Alur Kerja Transparan: Dari Analisis hingga Bayar
                            </SplitText>

                            <FadeUp delay={0.2}>
                                <p className="text-body-lg text-stone-gray leading-body-lg font-normal">
                                    Proses empat langkah yang memastikan efisiensi waktu, kepatuhan harga, dan keamanan rekonsiliasi pembayaran tanpa celah sengketa dokumen.
                                </p>
                            </FadeUp>

                            <FadeUp delay={0.3}>
                                <div className="bg-[#faf8f3] rounded-[2rem] border border-hairline-mist p-6 shadow-sm space-y-4">
                                    <div className="text-xs uppercase font-mono font-bold tracking-wider text-stone-gray">
                                        Tahapan Eksekusi Pengadaan
                                    </div>
                                    <div className="space-y-3">
                                        {workflowSteps.map((step, idx) => (
                                            <div key={idx} className="flex items-center gap-3 text-sm">
                                                <span className="w-7 h-7 rounded-full bg-pure-white border border-hairline-mist font-bold text-xs flex items-center justify-center text-ink-black shrink-0">
                                                    0{idx + 1}
                                                </span>
                                                <span className="font-medium text-ink-black text-xs sm:text-sm truncate">
                                                    {step.title}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                    <div className="pt-2 border-t border-sandstone/40 flex items-center justify-between text-xs text-stone-gray">
                                        <span className="flex items-center gap-1.5 font-semibold text-ink-black">
                                            <Check className="w-4 h-4 text-fresh-grass" />
                                            100% Digital & Terverifikasi
                                        </span>
                                    </div>
                                </div>
                            </FadeUp>

                            <FadeUp delay={0.4}>
                                <a
                                    href="#roles"
                                    className="inline-flex items-center gap-2 px-6 py-3 rounded-[50px] bg-ink-black text-pure-white hover:bg-pure-ink text-[14px] font-medium transition-all duration-300 shadow-sm hover:shadow-md group"
                                >
                                    <span>Lihat Hubungan Antar-Peran</span>
                                    <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                                </a>
                            </FadeUp>
                        </div>

                        {/* Right scrolling cards */}
                        <div className="lg:col-span-7 space-y-8">
                            {workflowSteps.map((item, index) => (
                                <FadeUp key={index} delay={index * 0.1}>
                                    <div className="bg-[#faf8f3] hover:bg-cream-light rounded-[2rem] border border-hairline-mist p-7 sm:p-9 shadow-sm transition-all duration-300 hover:shadow-md hover:-translate-y-1">
                                        <div className="flex items-center justify-between mb-6">
                                            <div className="flex items-center gap-3">
                                                <div className={`w-12 h-12 rounded-full ${item.pillBg} text-ink-black font-bold text-lg flex items-center justify-center border border-ink-black/10`}>
                                                    {item.step}
                                                </div>
                                                <span className="text-xs font-bold text-ink-black bg-pure-white border border-hairline-mist px-3.5 py-1.5 rounded-[50px]">
                                                    {item.badge}
                                                </span>
                                            </div>
                                            <span className="text-xs font-mono font-bold text-stone-gray">
                                                Fase 0{index + 1} / 04
                                            </span>
                                        </div>

                                        <h3 className="text-heading-md font-semibold text-ink-black tracking-tight mb-3 leading-snug">
                                            {item.title}
                                        </h3>

                                        <p className="text-body-sm sm:text-[16px] text-stone-gray leading-relaxed mb-6">
                                            {item.desc}
                                        </p>

                                        {index === 0 && (
                                            <div className="bg-pure-white rounded-2xl p-4 sm:p-5 border border-hairline-mist space-y-2 text-xs">
                                                <div className="flex justify-between items-center font-semibold text-ink-black">
                                                    <span>Penetapan Batas Pagu API Bank Indonesia</span>
                                                    <span className="text-fresh-grass bg-cream-paper px-2.5 py-0.5 rounded-[50px] border border-hairline-mist">
                                                        Terkunci
                                                    </span>
                                                </div>
                                                <p className="text-stone-gray">
                                                    PO diterbitkan otomatis dengan acuan harga pasar terverifikasi, menutup peluang mark-up pengadaan.
                                                </p>
                                            </div>
                                        )}

                                        {index === 1 && (
                                            <div className="bg-pure-white rounded-2xl p-4 sm:p-5 border border-hairline-mist space-y-2 text-xs">
                                                <div className="flex justify-between items-center font-semibold text-ink-black">
                                                    <span>Guided Bidding & Self-Reporting Logistics</span>
                                                    <span className="text-sky-pop bg-cream-paper px-2.5 py-0.5 rounded-[50px] border border-hairline-mist">
                                                        Portal Vendor
                                                    </span>
                                                </div>
                                                <p className="text-stone-gray">
                                                    Vendor mengonfirmasi harga dan menjadwalkan armada mandiri dengan estimasi waktu tiba transparan.
                                                </p>
                                            </div>
                                        )}

                                        {index === 2 && (
                                            <div className="bg-pure-white rounded-2xl p-4 sm:p-5 border border-hairline-mist space-y-2 text-xs">
                                                <div className="flex justify-between items-center font-semibold text-ink-black">
                                                    <span>Inspeksi Docking & Foto Mutu Fisik</span>
                                                    <span className="text-coral-pop bg-cream-paper px-2.5 py-0.5 rounded-[50px] border border-hairline-mist">
                                                        QC Gudang
                                                    </span>
                                                </div>
                                                <p className="text-stone-gray">
                                                    GRN digital diterbitkan hanya jika kondisi fisik sesuai; sengketa seketika membekukan dokumen pencairan dana.
                                                </p>
                                            </div>
                                        )}

                                        {index === 3 && (
                                            <div className="bg-pure-white rounded-2xl p-4 sm:p-5 border border-hairline-mist space-y-2 text-xs">
                                                <div className="flex justify-between items-center font-semibold text-ink-black">
                                                    <span>Otomasi 3-Way Match PO + GRN + Invoice</span>
                                                    <span className="text-fresh-grass bg-cream-paper px-2.5 py-0.5 rounded-[50px] border border-hairline-mist">
                                                        MATCHED ✓
                                                    </span>
                                                </div>
                                                <p className="text-stone-gray">
                                                    Sistem memverifikasi keselarasan kuantitas, harga, dan mutu sebelum approval pelunasan dibuka aman.
                                                </p>
                                            </div>
                                        )}

                                        <div className="mt-6 pt-4 border-t border-sandstone/30 flex items-center justify-between text-xs text-stone-gray">
                                            <span>Siklus Transaksi Terkendali</span>
                                            <ArrowRight className="w-4 h-4 text-ink-black" />
                                        </div>
                                    </div>
                                </FadeUp>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            {/* ═══════ ROLES ═══════ */}
            <section
                id="roles"
                className="relative bg-[#f5f0e4] border-b border-sandstone/40"
                style={{ paddingTop: 'var(--section-py)', paddingBottom: 'var(--section-py)' }}
            >
                <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center max-w-3xl mx-auto mb-14 space-y-5">
                        <FadeUp>
                            <Badge variant="coral" pill dotColor="bg-coral-pop">
                                Multi-Role Collaboration
                            </Badge>
                        </FadeUp>

                        <SplitText
                            as="h2"
                            className="text-heading-xl font-bold text-ink-black tracking-heading-xl leading-heading-xl"
                        >
                            Satu Platform, Nilai Manfaat Bagi Seluruh Peran
                        </SplitText>

                        <FadeUp delay={0.15}>
                            <p className="text-body-lg text-stone-gray leading-body-lg">
                                Dirancang untuk mengikis sekat komunikasi dan menyatukan kepentingan bisnis dalam rantai pasok pengadaan manufaktur.
                            </p>
                        </FadeUp>
                    </div>

                    <FadeUp>
                        <div className="flex flex-wrap items-center justify-center gap-2 sm:gap-3 mb-10">
                            {roleBenefits.map((roleItem, index) => (
                                <button
                                    key={index}
                                    type="button"
                                    onClick={() => setActiveRoleIndex(index)}
                                    className={`px-5 py-3 rounded-[50px] text-[14px] sm:text-body-sm font-medium transition-all duration-300 cursor-pointer ${activeRoleIndex === index
                                        ? 'bg-ink-black text-pure-white shadow-md'
                                        : 'bg-pure-white text-ink-black border border-hairline-mist hover:bg-cream-paper hover:shadow-sm'
                                        }`}
                                >
                                    {roleItem.role}
                                </button>
                            ))}
                        </div>
                    </FadeUp>

                    <FadeUp delay={0.1}>
                        <div className="bg-pure-white rounded-[2rem] border border-hairline-mist p-8 sm:p-12 transition-all overflow-hidden shadow-sm">
                            <AnimatePresence mode="wait">
                                <motion.div
                                    key={activeRoleIndex}
                                    initial={{ opacity: 0, y: 16 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    exit={{ opacity: 0, y: -16 }}
                                    transition={{ duration: 0.3, ease: "easeOut" }}
                                    className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center"
                                >
                                    <div className="lg:col-span-7 space-y-6">
                                        <div className="inline-block px-3.5 py-1.5 rounded-[50px] bg-cream-paper text-xs font-semibold text-ink-black border border-hairline-mist">
                                            Fokus Peran: {roleBenefits[activeRoleIndex].role}
                                        </div>
                                        <h3 className="text-heading-lg font-bold text-ink-black tracking-heading-lg leading-heading-lg">
                                            {roleBenefits[activeRoleIndex].title}
                                        </h3>
                                        <p className="text-body-lg text-stone-gray leading-relaxed">
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
                                                className="inline-flex items-center gap-2 px-6 py-3 rounded-[50px] bg-coral-pop text-pure-white text-body-sm font-medium hover:opacity-90 transition-all duration-300 shadow-sm hover:shadow-md group"
                                            >
                                                <span>{roleBenefits[activeRoleIndex].actionText}</span>
                                                <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                                            </Link>
                                            <a
                                                href="#workflow"
                                                className="inline-flex items-center gap-2 px-6 py-3 rounded-[50px] bg-cream-paper text-ink-black text-body-sm font-medium border border-hairline-mist hover:bg-sandstone/50 transition-colors duration-300"
                                            >
                                                Lihat Hubungan Antar-Peran
                                            </a>
                                        </div>
                                    </div>

                                    <div className="lg:col-span-5 bg-cream-paper rounded-[2rem] p-8 border border-sandstone/50 flex flex-col justify-between text-center space-y-6">
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
                                        <div className="text-xs text-stone-gray bg-pure-white p-4 rounded-2xl border border-hairline-mist">
                                            Berdasarkan benchmarking implementasi pengadaan bahan baku manufaktur berulang.
                                        </div>
                                    </div>
                                </motion.div>
                            </AnimatePresence>
                        </div>
                    </FadeUp>
                </div>
            </section>

            {/* ═══════ CALCULATOR ═══════ */}
            <section
                id="calculator"
                className="relative bg-[#232521] text-pure-white border-b border-white/10"
                style={{ paddingTop: 'var(--section-py)', paddingBottom: 'var(--section-py)' }}
            >
                <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                    <FadeUp>
                        <div className="bg-[#2c2f29] text-pure-white rounded-[2.5rem] p-8 sm:p-14 relative overflow-hidden shadow-2xl border border-white/10">
                            <div className="flex items-center gap-2 mb-10">
                                <span className="w-3 h-3 rounded-full bg-fresh-grass" />
                                <span className="w-3 h-3 rounded-full bg-sky-pop" />
                                <span className="w-3 h-3 rounded-full bg-coral-pop" />
                                <span className="text-xs font-mono uppercase text-[#a8aca5] ml-2 font-semibold">
                                    Simulasi Penghematan Anggaran Pengadaan
                                </span>
                            </div>

                            <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                                <div className="lg:col-span-6 space-y-6">
                                    <h2 className="text-heading-lg font-bold tracking-heading-lg leading-heading-lg text-pure-white">
                                        Hitung Potensi Efisiensi Anggaran Manufaktur Anda
                                    </h2>
                                    <p className="text-[#c2c5be] text-body-lg leading-relaxed">
                                        Geser nilai belanja bulanan bahan baku pabrik Anda untuk melihat estimasi nominal mark-up yang dapat dihemat melalui penetapan Harga Pagu cerdas.
                                    </p>

                                    <div className="bg-white/10 p-6 rounded-2xl border border-white/15 space-y-4">
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
                                        <div className="flex justify-between text-[11px] text-[#a8aca5]">
                                            <span>Rp 200 Juta</span>
                                            <span>Rp 5 Milyar</span>
                                            <span>Rp 10 Milyar</span>
                                        </div>
                                    </div>
                                </div>

                                <div className="lg:col-span-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div className="bg-pure-white text-ink-black p-6 rounded-2xl shadow-sm">
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

                                    <div className="bg-pure-white text-ink-black p-6 rounded-2xl shadow-sm">
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

                                    <div className="sm:col-span-2 bg-fresh-grass text-ink-black p-6 rounded-2xl flex items-center justify-between shadow-sm">
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
                                            className="px-6 py-3 rounded-[50px] bg-ink-black text-pure-white text-sm font-semibold hover:bg-pure-ink transition-all duration-300 shadow-sm hover:shadow-md"
                                        >
                                            Uji Sekarang
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </FadeUp>
                </div>
            </section>

            {/* ═══════ CTA ═══════ */}
            <section
                className="w-full relative overflow-hidden bg-[#eff8eb] border-b border-fresh-grass/25"
                style={{ paddingTop: 'var(--section-py)', paddingBottom: 'var(--section-py)' }}
            >
                <div className="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 space-y-7">
                    <FadeUp>
                        <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-[50px] bg-pure-white border border-hairline-mist text-xs font-semibold text-ink-black shadow-sm">
                            <span className="w-2.5 h-2.5 rounded-full bg-ink-black" />
                            <span>Transformasi Digital Pengadaan</span>
                        </div>
                    </FadeUp>

                    <SplitText
                        as="h2"
                        className="text-heading-xl font-bold text-ink-black tracking-heading-xl leading-heading-xl max-w-3xl mx-auto"
                    >
                        Siap Mengamankan Anggaran Bahan Baku Manufaktur Anda?
                    </SplitText>

                    <FadeUp delay={0.15}>
                        <p className="text-body-lg text-stone-gray leading-body-lg max-w-2xl mx-auto font-normal">
                            Koneksikan pengadaan pabrik Anda dengan data harga obyektif Bank Indonesia dan hilangkan sengketa invoice selamanya.
                        </p>
                    </FadeUp>

                    <FadeUp delay={0.25}>
                        <div className="flex flex-col sm:flex-row items-center justify-center gap-4 pt-2">
                            <Link
                                href="/login"
                                className="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-8 py-4 rounded-[50px] bg-ink-black hover:bg-pure-ink text-pure-white text-[16px] font-medium transition-all duration-300 shadow-sm hover:shadow-md group"
                            >
                                <span>Masuk Portal Pengadaan</span>
                                <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                            </Link>

                            <Link
                                href="/register-vendor"
                                className="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-8 py-4 rounded-[50px] bg-pure-white hover:bg-cream-paper text-ink-black border border-hairline-mist text-[16px] font-medium transition-all duration-300 shadow-sm"
                            >
                                <span>Daftar Menjadi Vendor Mitra</span>
                                <span className="w-2.5 h-2.5 rounded-full bg-coral-pop" />
                            </Link>
                        </div>
                    </FadeUp>

                    <FadeUp delay={0.35}>
                        <div className="flex flex-wrap items-center justify-center gap-3 sm:gap-4 pt-4">
                            {[
                                'Terintegrasi API Bank Indonesia',
                                'Audit Trail 3-Way Matching',
                                'Dispute Freezing Garansi'
                            ].map((label, i) => (
                                <span
                                    key={i}
                                    className="flex items-center gap-1.5 bg-pure-white/80 px-4 py-2.5 rounded-[50px] border border-hairline-mist text-xs font-medium text-ink-black"
                                >
                                    <Check className="w-4 h-4 text-fresh-grass" />
                                    {label}
                                </span>
                            ))}
                        </div>
                    </FadeUp>
                </div>
            </section>

            <Footer />
        </div>
    );
}