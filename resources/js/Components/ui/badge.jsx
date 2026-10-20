import React from 'react';
import { cn } from '@/lib/utils';

export function Badge({
    children,
    variant = 'default',
    pill = false,
    className = '',
    dotColor = null,
    ...props
}) {
    const baseStyles = 'inline-flex items-center gap-1.5 text-xs font-medium border transition-colors';
    const radius = pill ? 'rounded-full px-2.5 py-0.5' : 'rounded px-1.5 py-0.5';

    const variants = {
        // Brand
        brand: 'bg-brand-softer border-brand-subtle text-fg-brand-strong bg-emerald-50 border-emerald-200 text-emerald-700 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-400',

        // Alternative / Default
        alternative: 'bg-neutral-primary-soft border-default text-heading bg-white border-zinc-200 text-zinc-800 dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-200',
        default: 'bg-neutral-primary-soft border-default text-heading bg-white border-zinc-200 text-zinc-800 dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-200',

        // Gray / Secondary
        gray: 'bg-neutral-secondary-medium border-default-medium text-heading bg-zinc-100 border-zinc-200 text-zinc-700 dark:bg-zinc-800 dark:border-zinc-700 dark:text-zinc-300',
        secondary: 'bg-neutral-secondary-medium border-default-medium text-heading bg-zinc-100 border-zinc-200 text-zinc-700 dark:bg-zinc-800 dark:border-zinc-700 dark:text-zinc-300',

        // Danger
        danger: 'bg-danger-soft border-danger-subtle text-fg-danger-strong bg-red-50 border-red-200 text-red-700 dark:bg-red-950/40 dark:border-red-800 dark:text-red-400',
        destructive: 'bg-danger-soft border-danger-subtle text-fg-danger-strong bg-red-50 border-red-200 text-red-700 dark:bg-red-950/40 dark:border-red-800 dark:text-red-400',

        // Success / Green
        success: 'bg-success-soft border-success-subtle text-fg-success-strong bg-emerald-50 border-emerald-200 text-emerald-700 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-400',
        green: 'bg-success-soft border-success-subtle text-fg-success-strong bg-emerald-50 border-emerald-200 text-emerald-700 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-400',

        // Warning
        warning: 'bg-warning-soft border-warning-subtle text-fg-warning bg-amber-50 border-amber-200 text-amber-700 dark:bg-amber-950/40 dark:border-amber-800 dark:text-amber-400',
        yellow: 'bg-warning-soft border-warning-subtle text-fg-warning bg-amber-50 border-amber-200 text-amber-700 dark:bg-amber-950/40 dark:border-amber-800 dark:text-amber-400',

        // Compatibility variants
        outline: 'bg-transparent border-border text-foreground',
        white: 'bg-white border-zinc-200 text-zinc-900 dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-100',
        coral: 'bg-danger-soft border-danger-subtle text-fg-danger-strong bg-red-50 border-red-200 text-red-700 dark:bg-red-950/40 dark:border-red-800 dark:text-red-400',
        sky: 'bg-sky-50 border-sky-200 text-sky-700 dark:bg-sky-950/40 dark:border-sky-800 dark:text-sky-400',
        dark: 'bg-zinc-900 border-zinc-900 text-white dark:bg-zinc-100 dark:border-zinc-100 dark:text-zinc-900',
    };

    return (
        <span
            className={cn(
                baseStyles,
                radius,
                variants[variant] || variants.default,
                className
            )}
            {...props}
        >
            {dotColor && <span className={cn('size-1.5 rounded-full shrink-0', dotColor)} />}
            {children}
        </span>
    );
}

export default Badge;
