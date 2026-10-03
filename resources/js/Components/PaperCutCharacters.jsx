import React from 'react';

export function CharacterInspector({ className = "" }) {
    return (
        <svg viewBox="0 0 240 280" fill="none" xmlns="http://www.w3.org/2000/svg" className={className}>
            <ellipse cx="120" cy="255" rx="75" ry="14" fill="var(--color-sandstone)" />
            <path d="M72 165 C72 130 90 115 120 115 C150 115 168 130 168 165 L174 250 L66 250 Z" fill="var(--color-fresh-grass)" stroke="var(--color-ink-black)" strokeWidth="4" strokeLinejoin="round" />
            <path d="M102 115 L106 170 L134 170 L138 115 Z" fill="var(--color-pure-white)" stroke="var(--color-ink-black)" strokeWidth="3" />
            <polygon points="120,130 126,155 120,162 114,155" fill="var(--color-coral-pop)" stroke="var(--color-ink-black)" strokeWidth="2.5" />
            <circle cx="120" cy="72" r="38" fill="var(--color-cream-paper)" stroke="var(--color-ink-black)" strokeWidth="4" />
            <path d="M82 64 C88 32 152 32 158 64 C140 50 100 50 82 64 Z" fill="var(--color-ink-black)" />
            <circle cx="108" cy="74" r="5" fill="var(--color-ink-black)" />
            <circle cx="132" cy="74" r="5" fill="var(--color-ink-black)" />
            <path d="M112 88 Q120 95 128 88" stroke="var(--color-ink-black)" strokeWidth="3" strokeLinecap="round" fill="none" />
            <circle cx="120" cy="54" r="42" fill="none" stroke="var(--color-sky-pop)" strokeWidth="6" strokeDasharray="14 10" />
            <circle cx="148" cy="148" r="28" fill="var(--color-pure-white)" stroke="var(--color-ink-black)" strokeWidth="4" />
            <circle cx="148" cy="148" r="21" fill="var(--color-sky-pop)" fillOpacity="0.25" />
            <line x1="168" y1="168" x2="195" y2="195" stroke="var(--color-ink-black)" strokeWidth="6" strokeLinecap="round" />
            <rect x="42" y="152" width="44" height="60" rx="8" fill="var(--color-sunshine-pop)" stroke="var(--color-ink-black)" strokeWidth="3.5" transform="rotate(-12 42 152)" />
            <line x1="49" y1="168" x2="75" y2="162" stroke="var(--color-ink-black)" strokeWidth="3" strokeLinecap="round" />
            <line x1="47" y1="178" x2="73" y2="172" stroke="var(--color-ink-black)" strokeWidth="3" strokeLinecap="round" />
            <line x1="45" y1="188" x2="68" y2="182" stroke="var(--color-ink-black)" strokeWidth="3" strokeLinecap="round" />
        </svg>
    );
}

export function CharacterVendor({ className = "" }) {
    return (
        <svg viewBox="0 0 220 260" fill="none" xmlns="http://www.w3.org/2000/svg" className={className}>
            <ellipse cx="110" cy="240" rx="70" ry="12" fill="var(--color-sandstone)" />
            <path d="M68 150 C68 120 85 105 110 105 C135 105 152 120 152 150 L158 235 L62 235 Z" fill="var(--color-sky-pop)" stroke="var(--color-ink-black)" strokeWidth="4" strokeLinejoin="round" />
            <circle cx="110" cy="65" r="34" fill="var(--color-cream-paper)" stroke="var(--color-ink-black)" strokeWidth="4" />
            <path d="M78 55 C90 28 135 28 145 55 C132 46 95 46 78 55 Z" fill="var(--color-coral-pop)" stroke="var(--color-ink-black)" strokeWidth="3" />
            <rect x="74" y="52" width="72" height="10" rx="5" fill="var(--color-coral-pop)" stroke="var(--color-ink-black)" strokeWidth="3" />
            <circle cx="100" cy="68" r="4.5" fill="var(--color-ink-black)" />
            <circle cx="120" cy="68" r="4.5" fill="var(--color-ink-black)" />
            <path d="M104 80 Q110 86 116 80" stroke="var(--color-ink-black)" strokeWidth="3" strokeLinecap="round" fill="none" />
            <rect x="135" y="140" width="65" height="50" rx="10" fill="var(--color-sunshine-pop)" stroke="var(--color-ink-black)" strokeWidth="4" transform="rotate(8 135 140)" />
            <path d="M148 162 L185 168" stroke="var(--color-ink-black)" strokeWidth="3" strokeLinecap="round" />
            <circle cx="178" cy="148" r="7" fill="var(--color-fresh-grass)" stroke="var(--color-ink-black)" strokeWidth="2.5" />
        </svg>
    );
}

export function StickerBadge({ text = "HARGA PAGU BI", color = "var(--color-fresh-grass)", className = "" }) {
    return (
        <div 
            className={`inline-flex items-center gap-2 px-4 py-2 rounded-[50px] border-2 border-ink-black text-xs font-bold text-ink-black select-none ${className}`}
            style={{ backgroundColor: color }}
        >
            <span className="w-2.5 h-2.5 rounded-full bg-ink-black" />
            <span>{text}</span>
        </div>
    );
}

export function FloatingPencilCutout({ className = "" }) {
    return (
        <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" className={className}>
            <polygon points="50,10 65,30 55,90 45,90 35,30" fill="var(--color-sunshine-pop)" stroke="var(--color-ink-black)" strokeWidth="3" strokeLinejoin="round" />
            <polygon points="45,90 55,90 50,100" fill="var(--color-coral-pop)" stroke="var(--color-ink-black)" strokeWidth="3" />
            <circle cx="50" cy="22" r="5" fill="var(--color-sky-pop)" />
        </svg>
    );
}

export function PaperCutShapes({ className = "" }) {
    return (
        <svg viewBox="0 0 300 300" fill="none" xmlns="http://www.w3.org/2000/svg" className={className}>
            <path d="M40 90 C80 30 180 20 220 80 C260 140 240 220 180 260 C120 300 30 240 20 170 C10 120 10 130 40 90 Z" fill="var(--color-fresh-grass)" fillOpacity="0.25" stroke="var(--color-ink-black)" strokeWidth="3" strokeDasharray="8 6" />
            <circle cx="80" cy="200" r="36" fill="var(--color-coral-pop)" fillOpacity="0.8" stroke="var(--color-ink-black)" strokeWidth="3" />
            <rect x="180" y="50" width="60" height="60" rx="18" fill="var(--color-sunshine-pop)" stroke="var(--color-ink-black)" strokeWidth="3" transform="rotate(24 180 50)" />
            <polygon points="230,190 260,240 200,240" fill="var(--color-sky-pop)" stroke="var(--color-ink-black)" strokeWidth="3" strokeLinejoin="round" />
        </svg>
    );
}
