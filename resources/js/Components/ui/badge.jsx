import React from 'react';

export function Badge({
    children,
    variant = 'default',
    pill = false,
    className = '',
    dotColor = null,
    ...props
}) {
    const baseStyles = "inline-flex items-center gap-1.5 text-[13px] font-medium tracking-wide transition-colors";
    const radius = pill ? "rounded-[50px] px-3.5 py-1" : "rounded-[10px] px-2.5 py-1";

    const variants = {
        default: "bg-sandstone/70 text-ink-black border border-hairline-mist",
        white: "bg-pure-white text-ink-black border border-hairline-mist",
        green: "bg-fresh-grass/20 text-ink-black border border-fresh-grass/60",
        coral: "bg-coral-pop/15 text-ink-black border border-coral-pop/40",
        sky: "bg-sky-pop/15 text-ink-black border border-sky-pop/40",
        yellow: "bg-sunshine-pop/25 text-ink-black border border-sunshine-pop/60",
        dark: "bg-ink-black text-pure-white",
    };

    return (
        <span className={`${baseStyles} ${radius} ${variants[variant] || variants.default} ${className}`} {...props}>
            {dotColor && <span className={`w-2 h-2 rounded-full shrink-0 ${dotColor}`} />}
            {children}
        </span>
    );
}
