import { useEffect, useRef, useState } from 'react';
import { motion, useReducedMotion, useScroll, useSpring, useTransform } from 'framer-motion';
import { CircleCheck } from 'lucide-react';
import { cn } from '@/lib/utils';
import { LaptopFrame, PhoneFrame } from './DeviceFrames';
import LandingButton from './LandingButton';
import ProductMockup from './ProductMockup';

const SPRING = { stiffness: 120, damping: 30, mass: 0.4 };
const DESKTOP_QUERY = '(min-width: 1092px)';

/** Parallax distances in px, smaller on narrow screens so devices never cover the hero copy. */
const PARALLAX_RANGES = {
    desktop: { phone: [0, -120], sideCard: [0, -90], chip: [0, -70] },
    mobile: { phone: [0, -30], sideCard: [0, 0], chip: [0, -30] },
};

function FloatingChip({ icon: Icon, children, className }) {
    return (
        <div
            className={cn(
                'flex items-center gap-2 rounded-full border border-warm-80 bg-white px-4 py-2.5 type-body-xs font-medium whitespace-nowrap shadow-soft-md',
                className,
            )}
        >
            <Icon className="size-4 text-positive" />
            {children}
        </div>
    );
}

/**
 * Compact hero: headline, CTA and the device showcase fit in one desktop viewport.
 * The device group width is derived from the viewport height so the laptop and phone
 * are never cut off at the bottom of the first screen.
 * - Zoom scroll: the laptop starts slightly tilted back and flattens/zooms while the hero scrolls away.
 * - Parallax: phone, side card and chip move at different speeds.
 */
export default function HeroSection({ isAuthenticated, appName, logoUrl }) {
    const heroRef = useRef(null);
    const prefersReducedMotion = useReducedMotion();
    const [isDesktop, setIsDesktop] = useState(false);
    const ranges = PARALLAX_RANGES[isDesktop ? 'desktop' : 'mobile'];

    useEffect(() => {
        const mediaQuery = window.matchMedia(DESKTOP_QUERY);
        const onChange = () => setIsDesktop(mediaQuery.matches);
        onChange();
        mediaQuery.addEventListener('change', onChange);

        return () => mediaQuery.removeEventListener('change', onChange);
    }, []);

    const { scrollYProgress } = useScroll({ target: heroRef, offset: ['start start', 'end start'] });
    const progress = useSpring(scrollYProgress, SPRING);
    const laptopRotateX = useTransform(progress, [0, 0.5], [10, 0]);
    const laptopScale = useTransform(progress, [0, 0.5], [0.96, 1.06]);
    const phoneY = useTransform(progress, [0, 1], ranges.phone);
    const sideCardY = useTransform(progress, [0, 1], ranges.sideCard);
    const chipY = useTransform(progress, [0, 1], ranges.chip);

    const motionStyle = (style) => (prefersReducedMotion ? undefined : style);

    return (
        <section ref={heroRef} data-snap className="relative overflow-hidden bg-brand-offwhite pt-[66px] desk:pt-20">
            <div className="landing-container flex flex-col items-center pt-8 text-center desk:pt-6">
                <h1 className="max-w-[1000px] animate-fade-in-up type-hero whitespace-pre-line">
                    {'Pengadaan bahan baku,\nterkendali sampai lunas'}
                </h1>
                <p className="mt-4 max-w-[600px] animate-fade-in-up type-body-m text-warm-60 [animation-delay:80ms]">
                    Harga Pagu dari data Bank Indonesia, negosiasi per item, hingga 3-Way Matching sebelum pembayaran, dalam satu portal.
                </p>
                <div className="mt-6 flex w-full animate-fade-in-up flex-col items-center justify-center gap-3 [animation-delay:160ms] tab:w-auto tab:flex-row">
                    {isAuthenticated ? (
                        <LandingButton href="/dashboard" size="responsive">
                            Buka Dasbor
                        </LandingButton>
                    ) : (
                        <>
                            <LandingButton href="/register" size="responsive">
                                Daftar sebagai Perusahaan
                            </LandingButton>
                            <LandingButton href="/register/vendor" variant="secondary" size="responsive">
                                Daftar sebagai Vendor
                            </LandingButton>
                        </>
                    )}
                </div>
            </div>

            <div aria-hidden="true" className="landing-container relative mt-10 flex justify-center pb-12 [perspective:1600px] desk:mt-9 desk:pb-10">
                <div className="relative w-[90%] tab:w-[72%] desk:w-[clamp(480px,calc((100svh-460px)*1.48),860px)]">
                    <motion.div
                        style={motionStyle({ rotateX: laptopRotateX, scale: laptopScale })}
                        className="relative z-10 origin-bottom will-change-transform"
                    >
                        <div className="animate-fade-in-up [animation-delay:200ms]">
                            <LaptopFrame appName={appName} logoUrl={logoUrl} />
                        </div>
                    </motion.div>

                    <motion.div
                        style={motionStyle({ y: phoneY })}
                        className="absolute right-[-9%] bottom-[-3%] z-20 w-[25%] will-change-transform"
                    >
                        <div className="animate-fade-in-up [animation-delay:320ms]">
                            <PhoneFrame />
                        </div>
                    </motion.div>

                    <motion.div
                        style={motionStyle({ y: sideCardY })}
                        className="absolute top-[14%] left-[-40%] z-20 hidden w-[clamp(240px,38%,290px)] will-change-transform wide:block"
                    >
                        <div className="h-[290px] animate-float [--float-duration:7s] [--float-y:-12px]">
                            <ProductMockup type="negotiation" />
                        </div>
                    </motion.div>

                    <motion.div style={motionStyle({ y: chipY })} className="absolute bottom-[16%] left-[-6%] z-30 hidden tab:block">
                        <FloatingChip icon={CircleCheck} className="animate-float [--float-duration:7s] [--float-y:8px]">
                            3 dokumen cocok
                        </FloatingChip>
                    </motion.div>
                </div>
            </div>
        </section>
    );
}
