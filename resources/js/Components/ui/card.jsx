import React from 'react';

export function Card({
    children,
    className = '',
    surface = 'white',
    ...props
}) {
    const surfaces = {
        white: "bg-pure-white border border-hairline-mist/70",
        sandstone: "bg-sandstone border border-hairline-mist",
        cream: "bg-cream-paper border border-hairline-mist",
    };

    return (
        <div
            className={`rounded-[36px] sm:rounded-[50px] p-6 sm:p-8 transition-all ${surfaces[surface] || surfaces.white} ${className}`}
            {...props}
        >
            {children}
        </div>
    );
}

export function CardHeader({ children, className = '', ...props }) {
    return <div className={`flex flex-col space-y-2 mb-4 ${className}`} {...props}>{children}</div>;
}

export function CardTitle({ children, className = '', ...props }) {
    return <h3 className={`text-[24px] sm:text-[28px] font-medium leading-[1.2] text-ink-black tracking-tight ${className}`} {...props}>{children}</h3>;
}

export function CardDescription({ children, className = '', ...props }) {
    return <p className={`text-body-sm sm:text-[16px] leading-[1.5] text-stone-gray ${className}`} {...props}>{children}</p>;
}

export function CardContent({ children, className = '', ...props }) {
    return <div className={`mt-2 ${className}`} {...props}>{children}</div>;
}

export function CardFooter({ children, className = '', ...props }) {
    return <div className={`flex items-center pt-4 mt-4 border-t border-hairline-mist/40 ${className}`} {...props}>{children}</div>;
}
