import React from 'react';

export function Button({
    children,
    variant = 'default',
    size = 'default',
    className = '',
    iconDot = null,
    as: Component = 'button',
    ...props
}) {
    const baseStyles = "inline-flex items-center justify-center font-medium transition-all duration-200 focus-visible:outline-none cursor-pointer disabled:pointer-events-none disabled:opacity-50 select-none text-body-sm";

    const variants = {
        default: "bg-pure-white text-ink-black border border-hairline-mist hover:border-ink-black hover:bg-cream-paper/40 shadow-none",
        coral: "bg-coral-pop text-pure-white hover:opacity-95 shadow-none",
        green: "bg-fresh-grass text-ink-black hover:opacity-95 shadow-none font-semibold",
        dark: "bg-ink-black text-pure-white hover:bg-pure-ink shadow-none",
        ghost: "bg-transparent text-ink-black hover:bg-sandstone/40",
        outline: "bg-transparent border border-ink-black text-ink-black hover:bg-ink-black hover:text-pure-white",
    };

    const sizes = {
        default: "h-[46px] px-5 py-[11px] rounded-[50px]",
        sm: "h-[38px] px-4 py-2 text-[14px] rounded-[50px]",
        lg: "h-[54px] px-7 py-3 text-[16px] rounded-[50px]",
        icon: "w-[42px] h-[42px] rounded-full p-0 flex items-center justify-center",
    };

    return (
        <Component
            className={`${baseStyles} ${variants[variant] || variants.default} ${sizes[size] || sizes.default} ${className}`}
            {...props}
        >
            <span className="flex items-center gap-2">
                {children}
                {iconDot && (
                    <span
                        className={`w-2.5 h-2.5 rounded-full inline-block shrink-0 ${typeof iconDot === 'string' ? iconDot : 'bg-sky-pop'
                            }`}
                    />
                )}
            </span>
        </Component>
    );
}
