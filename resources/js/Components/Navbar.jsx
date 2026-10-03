import React, { useState, useEffect, useRef } from 'react';
import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';
import landingData from '@/data/landingData.json';
import Logo from '../../assets/logo/logo.webp';

const menuContainerVariants = {
    closed: {
        opacity: 0,
        y: -10,
        scale: 0.98,
        transition: {
            duration: 0.2,
            ease: [0.4, 0, 0.2, 1],
            staggerChildren: 0.03,
            staggerDirection: -1,
            when: "afterChildren",
        },
    },
    open: {
        opacity: 1,
        y: 0,
        scale: 1,
        transition: {
            duration: 0.32,
            ease: [0.16, 1, 0.3, 1],
            staggerChildren: 0.05,
            delayChildren: 0.04,
        },
    },
};

const menuItemVariants = {
    closed: {
        opacity: 0,
        x: -10,
        transition: {
            duration: 0.15,
            ease: "easeInOut",
        },
    },
    open: {
        opacity: 1,
        x: 0,
        transition: {
            duration: 0.28,
            ease: [0.16, 1, 0.3, 1],
        },
    },
};

export default function Navbar({ className = '' }) {
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [isScrolled, setIsScrolled] = useState(false);
    const navContainerRef = useRef(null);

    useEffect(() => {
        const handleScroll = () => {
            setIsScrolled(window.scrollY > 20);
        };
        handleScroll();
        window.addEventListener('scroll', handleScroll, { passive: true });
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    // Close mobile menu on click outside, Escape key, or when resizing to desktop
    useEffect(() => {
        const handleOutsideClick = (event) => {
            if (navContainerRef.current && !navContainerRef.current.contains(event.target)) {
                setMobileMenuOpen(false);
            }
        };

        const handleKeyDown = (event) => {
            if (event.key === 'Escape') {
                setMobileMenuOpen(false);
            }
        };

        const handleResize = () => {
            if (window.innerWidth >= 1024) {
                setMobileMenuOpen(false);
            }
        };

        if (mobileMenuOpen) {
            document.addEventListener('mousedown', handleOutsideClick);
            document.addEventListener('touchstart', handleOutsideClick, { passive: true });
            document.addEventListener('keydown', handleKeyDown);
        }
        window.addEventListener('resize', handleResize, { passive: true });

        return () => {
            document.removeEventListener('mousedown', handleOutsideClick);
            document.removeEventListener('touchstart', handleOutsideClick);
            document.removeEventListener('keydown', handleKeyDown);
            window.removeEventListener('resize', handleResize);
        };
    }, [mobileMenuOpen]);

    return (
        <header
            className={`sticky top-0 z-50 pt-3 sm:pt-4 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto ${className}`}
        >
            <div ref={navContainerRef} className="relative w-full">
                <nav className={`rounded-[16px] px-4 sm:px-6 py-2.5 flex items-center justify-between transition-all duration-300 bg-white shadow-sm`}>
                    <Link href="/" className="flex items-center gap-2.5 sm:gap-3 group shrink-0">
                        <img src={Logo} className="w-8 sm:w-9 h-auto" alt="Logo PAGORA" />
                        <div className="flex flex-col">
                            <span className="font-semibold text-base sm:text-lg lg:text-xl tracking-tight text-ink-black leading-none">
                                PAGORA
                            </span>
                            <span className="text-[9px] sm:text-[10px] text-stone-gray tracking-wide font-medium mt-0.5 hidden sm:inline">
                                B2B E-PROCUREMENT
                            </span>
                        </div>
                    </Link>

                    <div className="hidden lg:flex items-center gap-1 xl:gap-2">
                        {landingData.navLinks.map((item, idx) => (
                            <a
                                key={idx}
                                href={item.href}
                                className="
                                    relative
                                    overflow-hidden
                                    rounded-[24px]
                                    px-4 py-2
                                    text-body-lg
                                    font-medium
                                    text-ink-black
                                    group
                                "
                            >
                                <span
                                    className="
                                    absolute
                                    inset-0
                                    scale-0
                                    bg-fresh-grass/70
                                    transition-transform
                                    duration-100
                                    ease-out
                                    group-hover:scale-100
                                    "
                                />

                                <span className="relative z-10 group-hover:font-semibold transition-all duration-100 ease-out">
                                    {item.title}
                                </span>
                            </a>
                        ))}
                    </div>

                    <div className="flex items-center gap-2 sm:gap-3">
                        <Link
                            href="/login"
                            className="hidden sm:inline-flex items-center gap-2 px-4 sm:px-5 py-2 sm:py-2.5 rounded-[50px] bg-ink-black border border-pure-white text-pure-white text-[13px] sm:text-[14px] lg:text-[15px] font-medium hover:bg-pure-ink active:scale-95 transition-all group shrink-0"
                        >
                            <span>Masuk Portal</span>
                            <span className="w-2 h-2 rounded-full bg-sky-pop group-hover:bg-fresh-grass transition-colors" />
                        </Link>

                        {/* Burger Button with smooth morphing bars */}
                        <button
                            type="button"
                            onClick={() => setMobileMenuOpen(prev => !prev)}
                            aria-expanded={mobileMenuOpen}
                            aria-label={mobileMenuOpen ? "Tutup menu navigasi" : "Buka menu navigasi"}
                            className="lg:hidden relative w-10 h-10 rounded-full bg-fresh-grass border border-ink-black text-ink-black flex flex-col items-center justify-center gap-[5px] hover:opacity-90 active:scale-90 transition-transform cursor-pointer shrink-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-ink-black focus-visible:ring-offset-2"
                        >
                            <motion.span
                                animate={mobileMenuOpen ? { rotate: 45, y: 7 } : { rotate: 0, y: 0 }}
                                transition={{ duration: 0.28, ease: [0.16, 1, 0.3, 1] }}
                                className="w-[18px] h-[2px] bg-ink-black rounded-full block origin-center"
                            />
                            <motion.span
                                animate={mobileMenuOpen ? { opacity: 0, scaleX: 0 } : { opacity: 1, scaleX: 1 }}
                                transition={{ duration: 0.2, ease: "easeOut" }}
                                className="w-[18px] h-[2px] bg-ink-black rounded-full block origin-center"
                            />
                            <motion.span
                                animate={mobileMenuOpen ? { rotate: -45, y: -7 } : { rotate: 0, y: 0 }}
                                transition={{ duration: 0.28, ease: [0.16, 1, 0.3, 1] }}
                                className="w-[18px] h-[2px] bg-ink-black rounded-full block origin-center"
                            />
                        </button>
                    </div>
                </nav>

                {/* Mobile Dropdown Menu: Absolutely positioned so it does NOT shift content underneath */}
                <AnimatePresence>
                    {mobileMenuOpen && (
                        <motion.div
                            key="mobile-nav-menu"
                            initial="closed"
                            animate="open"
                            exit="closed"
                            variants={menuContainerVariants}
                            className={`lg:hidden absolute top-[calc(100%+0.5rem)] left-0 right-0 rounded-[32px] sm:rounded-[36px] border p-4 sm:p-5 shadow-2xl space-y-3 transition-colors duration-300 max-h-[calc(100vh-5.5rem)] overflow-y-auto ${isScrolled
                                    ? 'bg-pure-white/95 backdrop-blur-xl border-hairline-mist/80 shadow-[0_20px_45px_rgba(44,46,42,0.12)]'
                                    : 'bg-pure-white/95 backdrop-blur-xl border-hairline-mist shadow-[0_20px_45px_rgba(44,46,42,0.08)]'
                                }`}
                        >
                            <div className="flex flex-col space-y-1">
                                {landingData.navLinks.map((item, idx) => (
                                    <motion.a
                                        key={idx}
                                        variants={menuItemVariants}
                                        href={item.href}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className="group flex items-center justify-between px-4 py-2.5 rounded-[50px] text-[15px] font-medium text-ink-black hover:bg-cream-paper active:bg-sandstone/60 transition-colors"
                                    >
                                        <div className="flex items-center gap-2.5">
                                            <span className="w-1.5 h-1.5 rounded-full bg-stone-gray/40 group-hover:bg-fresh-grass group-hover:scale-125 transition-all" />
                                            <span>{item.title}</span>
                                        </div>
                                        <ArrowRight className="w-4 h-4 text-stone-gray opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-200" />
                                    </motion.a>
                                ))}
                            </div>

                            <motion.div
                                variants={menuItemVariants}
                                className="pt-3 border-t border-hairline-mist/60 flex flex-col gap-2.5"
                            >
                                <Link
                                    href="/register-vendor"
                                    onClick={() => setMobileMenuOpen(false)}
                                    className="w-full py-3.5 px-5 inline-flex items-center justify-center gap-2 rounded-[50px] bg-fresh-grass border border-ink-black text-ink-black text-[15px] font-medium hover:brightness-105 active:scale-[0.99] transition-all shadow-xs"
                                >
                                    <span>Daftar Menjadi Vendor Mitra</span>
                                    <span className="w-2 h-2 rounded-full bg-coral-pop" />
                                </Link>
                                <Link
                                    href="/login"
                                    onClick={() => setMobileMenuOpen(false)}
                                    className="w-full py-3.5 px-5 inline-flex items-center justify-center gap-2 rounded-[50px] bg-ink-black text-pure-white text-[15px] font-medium hover:bg-pure-ink active:scale-[0.99] transition-all shadow-xs"
                                >
                                    <span>Masuk Portal Pengadaan</span>
                                    <span className="w-2 h-2 rounded-full bg-sky-pop" />
                                </Link>
                            </motion.div>
                        </motion.div>
                    )}
                </AnimatePresence>
            </div>
        </header>
    );
}