import { motion } from 'framer-motion';

const EASE_OUT_QUICK = [0.22, 1, 0.36, 1];
const VIEWPORT = { once: true, amount: 0.25 };

/**
 * Scroll reveal: fades and lifts its content once it enters the viewport.
 * Transforms are skipped automatically for reduced motion by the page-level `MotionConfig`.
 */
export function Reveal({ as = 'div', delay = 0, y = 28, className, children, ...props }) {
    const Component = motion[as];

    return (
        <Component
            initial={{ opacity: 0, y }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={VIEWPORT}
            transition={{ duration: 0.7, ease: EASE_OUT_QUICK, delay }}
            className={className}
            {...props}
        >
            {children}
        </Component>
    );
}

const staggerContainer = {
    hidden: {},
    visible: { transition: { staggerChildren: 0.09, delayChildren: 0.05 } },
};

const staggerItem = {
    hidden: { opacity: 0, y: 32 },
    visible: { opacity: 1, y: 0, transition: { duration: 0.6, ease: EASE_OUT_QUICK } },
};

/** Stagger animation: children rendered with `StaggerItem` appear one after another. */
export function Stagger({ as = 'ul', className, children, ...props }) {
    const Component = motion[as];

    return (
        <Component initial="hidden" whileInView="visible" viewport={VIEWPORT} variants={staggerContainer} className={className} {...props}>
            {children}
        </Component>
    );
}

export function StaggerItem({ as = 'li', className, children, ...props }) {
    const Component = motion[as];

    return (
        <Component variants={staggerItem} className={className} {...props}>
            {children}
        </Component>
    );
}
