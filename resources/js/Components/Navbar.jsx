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
            className={`fixed top-0 inset-x-0 z-50 pt-3 sm:pt-4 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto pointer-events-none ${className}`}
        >
            <div ref={navContainerRef} className="relative w-full pointer-events-auto flex items-stretch justify-between gap-3 sm:gap-4">
                {/* Container 1: Navigasi */}
                <nav className={`flex-1 rounded-xl border px-4 sm:px-6 py-2.5 flex items-center justify-between transition-all duration-300 ${isScrolled ? 'bg-pure-white/85 backdrop-blur-xl shadow-md border-hairline-mist/60' : 'bg-pure-white border-hairline-mist shadow-xs'}`}>
                    <Link href="/" className="flex items-center gap-2.5 sm:gap-3 group shrink-0">
                        <img src={Logo} className="w-8 sm:w-9 h-auto" alt="Logo PAGORA" />
                        <div className="flex flex-col">
                            <span className="font-semibold text-lg tracking-tight text-ink-black leading-none">
                                PAGORA
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
                                    rounded-full
                                    px-4 py-2
                                    text-body-md
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
                                    bg-cream-paper/90
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

                    <button
                        type="button"
                        onClick={() => setMobileMenuOpen(prev => !prev)}
                        aria-expanded={mobileMenuOpen}
                        aria-label={mobileMenuOpen ? "Tutup menu navigasi" : "Buka menu navigasi"}
                        className="lg:hidden relative w-10 h-10 rounded-lg bg-fresh-grass border border-ink-black text-ink-black flex flex-col items-center justify-center gap-1.25 hover:opacity-90 active:scale-90 transition-transform cursor-pointer shrink-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-ink-black focus-visible:ring-offset-2"
                    >
                        <motion.span
                            animate={mobileMenuOpen ? { rotate: 45, y: 7 } : { rotate: 0, y: 0 }}
                            transition={{ duration: 0.28, ease: [0.16, 1, 0.3, 1] }}
                            className="w-4.5 h-0.5 bg-ink-black rounded-xs block origin-center"
                        />
                        <motion.span
                            animate={mobileMenuOpen ? { opacity: 0, scaleX: 0 } : { opacity: 1, scaleX: 1 }}
                            transition={{ duration: 0.2, ease: "easeOut" }}
                            className="w-4.5 h-0.5 bg-ink-black rounded-xs block origin-center"
                        />
                        <motion.span
                            animate={mobileMenuOpen ? { rotate: -45, y: -7 } : { rotate: 0, y: 0 }}
                            transition={{ duration: 0.28, ease: [0.16, 1, 0.3, 1] }}
                            className="w-4.5 h-0.5 bg-ink-black rounded-xs block origin-center"
                        />
                    </button>
                </nav>

                {/* Container 2: Masuk Portal */}
                <Link
                    href="/login"
                    className={`hidden sm:inline-flex items-center gap-3 px-4 sm:px-5 rounded-xl border transition-all duration-300 group shrink-0 ${
                        isScrolled
                            ? 'bg-pure-white/85 backdrop-blur-xl shadow-md border-hairline-mist/60'
                            : 'bg-pure-white border-hairline-mist shadow-xs'
                    } hover:border-ink-black/40 hover:shadow-md active:scale-95`}
                >
                    <span className="font-semibold text-ink-black text-body-md tracking-tight">
                        Masuk Portal
                    </span>
                    <div className="w-8 h-8 sm:w-8.5 sm:h-8.5 shrink-0 flex items-center justify-center">
                        <svg viewBox="0 0 100 100" className="w-full h-full" fill="none" aria-hidden="true">
                            <defs>
                                <clipPath id="avatar-face-clip">
                                    <circle cx="50" cy="50" r="50" />
                                </clipPath>
                            </defs>
                            {/* Blue circular face */}
                            <circle cx="50" cy="50" r="50" fill="#2ba0ff" />
                            {/* Sunglasses / Top Hair */}
                            <g clipPath="url(#avatar-face-clip)">
                                <path
                                    d="M -5 26 C 10 43, 38 43, 47 33 C 49 31, 51 31, 53 33 C 62 43, 90 43, 105 26 L 105 -5 L -5 -5 Z"
                                    fill="#2c2e2a"
                                />
                            </g>
                            {/* Nose */}
                            <path
                                d="M 46 47 C 46 41, 54 41, 54 47"
                                stroke="#2c2e2a"
                                strokeWidth="4"
                                strokeLinecap="round"
                                fill="none"
                            />
                            {/* Open Mouth */}
                            <path
                                d="M 28 56 Q 50 53 72 56 C 72 78, 28 78, 28 56 Z"
                                fill="#2c2e2a"
                            />
                            {/* Teeth */}
                            <path
                                d="M 30 56 Q 50 53 70 56 C 70 63, 30 63, 30 56 Z"
                                fill="#ffffff"
                            />
                            {/* Tongue */}
                            <path
                                d="M 37 73 C 42 63, 58 63, 63 73 C 55 77, 45 77, 37 73 Z"
                                fill="#ff705d"
                            />
                        </svg>
                    </div>
                </Link>

                {/* Mobile Dropdown Menu: Absolutely positioned so it does NOT shift content underneath */}
                <AnimatePresence>
                    {mobileMenuOpen && (
                        <motion.div
                            key="mobile-nav-menu"
                            initial="closed"
                            animate="open"
                            exit="closed"
                            variants={menuContainerVariants}
                            className={`lg:hidden absolute top-[calc(100%+0.5rem)] left-0 right-0 rounded-xl sm:rounded-2xl border p-4 sm:p-5 shadow-xl space-y-3 transition-colors duration-300 max-h-[calc(100vh-5.5rem)] overflow-y-auto ${isScrolled
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
                                        className="group flex items-center justify-between px-4 py-2.5 rounded-lg text-body-sm font-medium text-ink-black hover:bg-cream-paper active:bg-sandstone/60 transition-colors"
                                    >
                                        <div className="flex items-center gap-2.5">
                                            <span className="w-1.5 h-1.5 rounded-xs bg-stone-gray/40 group-hover:bg-fresh-grass group-hover:scale-125 transition-all" />
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
                                    href="/login"
                                    onClick={() => setMobileMenuOpen(false)}
                                    className="w-full py-3.5 px-5 inline-flex items-center justify-center gap-2 rounded-lg bg-ink-black text-pure-white text-body-sm font-medium hover:bg-pure-ink active:scale-[0.99] transition-all shadow-xs"
                                >
                                    <span>Masuk Portal Pengadaan</span>
                                    <span className="w-2 h-2 rounded-xs bg-sky-pop" />
                                </Link>
                            </motion.div>
                        </motion.div>
                    )}
                </AnimatePresence>
            </div>
        </header>
    );
}