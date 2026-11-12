import { useLayoutEffect, useRef } from 'react';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import LandingButton from './LandingButton';
import LandingImage from './LandingImage';

gsap.registerPlugin(ScrollTrigger);

/** Dark closing banner over a warehouse photo. Parallax: photo, gradient glow and copy drift at different speeds. */
export default function ClosingBanner({ isAuthenticated }) {
    const bannerRef = useRef(null);
    const photoRef = useRef(null);
    const glowRef = useRef(null);
    const copyRef = useRef(null);

    useLayoutEffect(() => {
        const mm = gsap.matchMedia();

        mm.add('(prefers-reduced-motion: no-preference)', () => {
            const scrollTrigger = { trigger: bannerRef.current, start: 'top bottom', end: 'bottom top', scrub: true };
            gsap.fromTo(photoRef.current, { yPercent: -8, scale: 1.08 }, { yPercent: 8, scale: 1, ease: 'none', scrollTrigger });
            gsap.fromTo(glowRef.current, { yPercent: 35, scale: 0.85 }, { yPercent: -25, scale: 1.15, ease: 'none', scrollTrigger });
            gsap.fromTo(copyRef.current, { y: 60 }, { y: -30, ease: 'none', scrollTrigger });
        });

        return () => mm.revert();
    }, []);

    return (
        <div className="landing-container">
            <div
                ref={bannerRef}
                data-focus-theme="dark"
                className="relative isolate overflow-hidden rounded-small bg-brand-offblack px-6 py-20 text-center text-white desk:rounded-medium desk:py-28"
            >
                <div ref={photoRef} aria-hidden="true" className="absolute -inset-y-16 inset-x-0 -z-10 will-change-transform">
                    <LandingImage name="banner-gudang" isDecorative className="opacity-50 grayscale" />
                </div>
                <div
                    aria-hidden="true"
                    className="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_center,#14140fa6_0%,#14140ff0_75%)]"
                />
                <div
                    ref={glowRef}
                    aria-hidden="true"
                    className="absolute -bottom-40 left-1/2 -z-10 ml-[-260px] size-[520px] rounded-full bg-[conic-gradient(from_220deg,#82dcfa,#c8a0ff,#fab9fa,#ff8c5f,#beff50,#82dcfa)] opacity-30 blur-3xl will-change-transform"
                />
                <div ref={copyRef} className="will-change-transform">
                    <span className="inline-flex rounded-full border border-white/20 px-4 py-1.5 type-eyebrow text-warm-80">
                        Perusahaan · Vendor · Satu portal
                    </span>
                    <h2 className="mx-auto mt-6 max-w-[1000px] type-heading-l whitespace-pre-line">{'Anda yang memutuskan.\nPAGORA yang mencatat.'}</h2>
                    <div className="mt-10 flex justify-center">
                        <LandingButton href={isAuthenticated ? '/dashboard' : '/register'} theme="dark">
                            {isAuthenticated ? 'Buka Dasbor' : 'Mulai sekarang'}
                        </LandingButton>
                    </div>
                </div>
            </div>
        </div>
    );
}
