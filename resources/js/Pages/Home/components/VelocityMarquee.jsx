import { useRef } from 'react';
import {
    motion,
    useAnimationFrame,
    useMotionValue,
    useReducedMotion,
    useScroll,
    useSpring,
    useTransform,
    useVelocity,
    wrap,
} from 'framer-motion';
import { cn } from '@/lib/utils';

/**
 * Velocity scroll marquee: drifts at `baseVelocity` (% of one copy per second), speeds up and
 * leans (skew) with scroll speed, and follows the scroll direction. Content is rendered twice
 * so the -50% wrap is seamless.
 */
export default function VelocityMarquee({ children, baseVelocity = -2, maxSkew = 6, className }) {
    const prefersReducedMotion = useReducedMotion();
    const baseX = useMotionValue(0);
    const { scrollY } = useScroll();
    const scrollVelocity = useVelocity(scrollY);
    const smoothVelocity = useSpring(scrollVelocity, { damping: 50, stiffness: 400 });
    const velocityFactor = useTransform(smoothVelocity, [0, 1000], [0, 4], { clamp: false });
    const skewX = useTransform(smoothVelocity, [-2500, 2500], [maxSkew, -maxSkew]);
    const x = useTransform(baseX, (value) => `${wrap(-50, 0, value)}%`);
    const direction = useRef(1);

    useAnimationFrame((_, delta) => {
        if (prefersReducedMotion) {
            return;
        }

        const factor = velocityFactor.get();
        if (factor < 0) {
            direction.current = -1;
        } else if (factor > 0) {
            direction.current = 1;
        }

        let moveBy = direction.current * baseVelocity * (delta / 1000);
        moveBy += moveBy * Math.abs(factor);
        baseX.set(baseX.get() + moveBy);
    });

    return (
        <div className={cn('overflow-hidden', className)}>
            <motion.div
                className="flex w-max will-change-transform"
                style={prefersReducedMotion ? undefined : { x, skewX }}
            >
                {children}
                <div aria-hidden="true" className="flex shrink-0">
                    {children}
                </div>
            </motion.div>
        </div>
    );
}
