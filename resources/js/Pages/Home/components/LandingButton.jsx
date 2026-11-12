import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { cn } from '@/lib/utils';

const VARIANT_CLASSES = {
    primary: {
        light: 'border-transparent bg-brand-lime text-brand-offblack',
        dark: 'border-transparent bg-brand-lime text-brand-offblack',
        green: 'border-transparent bg-brand-offblack text-brand-offwhite',
    },
    secondary: {
        light: 'border-brand-offblack bg-transparent text-brand-offblack hover:border-transparent hover:bg-brand-lime',
        dark: 'border-white bg-transparent text-white hover:border-transparent hover:bg-brand-lime hover:text-brand-offblack',
        green: 'border-brand-offblack bg-transparent text-brand-offblack hover:bg-brand-offblack hover:text-brand-offwhite',
    },
    tertiary: {
        light: 'border-transparent bg-transparent text-brand-offblack hover:text-warm-60',
        dark: 'border-transparent bg-transparent text-white hover:text-warm-80',
        green: 'border-transparent bg-transparent text-brand-offblack hover:text-brand-offwhite',
    },
};

const SIZE_CLASSES = {
    small: 'h-10 px-5 text-[15px]',
    default: 'h-12 px-6 text-base',
    responsive: 'h-12 w-full px-6 text-base tab:w-auto',
};

/**
 * Pill button from DESIGN.md §5.1. Internal paths use Inertia `Link`, `#anchor` uses a plain anchor.
 * The trailing arrow swaps for a second arrow sliding in on hover.
 */
export default function LandingButton({
    href,
    children,
    variant = 'primary',
    theme = 'light',
    size = 'default',
    withIcon = true,
    className,
    ...props
}) {
    const isTertiary = variant === 'tertiary';
    const classes = cn(
        'group/button relative inline-flex shrink-0 items-center justify-center gap-2 overflow-hidden rounded-full border font-medium whitespace-nowrap',
        'transition-[color,background-color,border-color] duration-200 ease-standard',
        !isTertiary &&
            'before:pointer-events-none before:absolute before:inset-0 before:bg-brand-offblack/0 before:transition-colors before:duration-200 hover:before:bg-brand-offblack/[0.06] active:before:bg-brand-offblack/[0.12]',
        VARIANT_CLASSES[variant][theme],
        SIZE_CLASSES[size],
        isTertiary && 'h-auto px-0',
        className,
    );

    const content = (
        <>
            <span className={cn('relative', isTertiary && 'underline underline-offset-4 group-hover/button:no-underline')}>
                {children}
            </span>
            {withIcon && (
                <span aria-hidden="true" className="relative size-4 overflow-hidden">
                    <ArrowRight className="absolute inset-0 size-4 transition-transform duration-300 ease-out-quick group-hover/button:translate-x-full" />
                    <ArrowRight className="absolute inset-0 size-4 -translate-x-full transition-transform duration-300 ease-out-quick group-hover/button:translate-x-0" />
                </span>
            )}
        </>
    );

    if (href.startsWith('#')) {
        return (
            <a href={href} className={classes} {...props}>
                {content}
            </a>
        );
    }

    return (
        <Link href={href} className={classes} {...props}>
            {content}
        </Link>
    );
}
