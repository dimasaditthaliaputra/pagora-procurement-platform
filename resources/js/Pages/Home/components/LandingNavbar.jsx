import { useEffect, useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import { ArrowRight, ChevronDown, Menu, X } from 'lucide-react';
import { cn } from '@/lib/utils';
import { navigation } from '@/data/landingContent';
import LandingButton from './LandingButton';
import { useSmoothScroll } from './SmoothScrollProvider';

const DESKTOP_QUERY = '(min-width: 1092px)';
const DROPDOWN_CLOSE_DELAY_MS = 150;

function AuthActions({ isAuthenticated, size = 'small', className }) {
    if (isAuthenticated) {
        return (
            <div className={className}>
                <LandingButton href="/dashboard" size={size}>
                    Buka Dasbor
                </LandingButton>
            </div>
        );
    }

    return (
        <div className={className}>
            <LandingButton href="/login" variant="secondary" size={size} withIcon={false}>
                Masuk
            </LandingButton>
            <LandingButton href="/register" size={size}>
                Daftar
            </LandingButton>
        </div>
    );
}

function DesktopDropdown({ group, isOpen, onOpen, onClose, onToggle }) {
    const triggerRef = useRef(null);
    const panelId = `nav-panel-${group.label.toLowerCase()}`;

    const onKeyDown = (event) => {
        if (event.key === 'Escape' && isOpen) {
            onClose();
            triggerRef.current?.focus();
        }
    };

    const onBlur = (event) => {
        if (!event.currentTarget.contains(event.relatedTarget)) {
            onClose();
        }
    };

    return (
        <div onMouseEnter={onOpen} onMouseLeave={onClose} onKeyDown={onKeyDown} onBlur={onBlur}>
            <button
                ref={triggerRef}
                type="button"
                aria-expanded={isOpen}
                aria-controls={panelId}
                onClick={(event) => (event.detail === 0 ? onToggle() : onOpen())}
                className={cn(
                    'flex items-center gap-1 rounded-full px-3 py-2 type-body-xs font-medium transition-colors duration-200 hover:text-warm-60 desk:text-[15px]',
                    isOpen && 'text-warm-60',
                )}
            >
                {group.label}
                <ChevronDown className={cn('size-4 transition-transform duration-300', isOpen && 'rotate-180')} />
            </button>
            <div
                id={panelId}
                className={cn(
                    'absolute inset-x-0 top-full pt-4 transition-[opacity,transform,visibility] duration-300 ease-standard',
                    isOpen ? 'visible translate-y-0 opacity-100' : 'invisible -translate-y-2 opacity-0',
                )}
            >
                <div className="rounded-medium bg-white px-10 pt-9 pb-6 shadow-soft-popover">
                    <p className="type-body-xs text-warm-60">Ringkasan produk</p>
                    <p className="mt-2 type-heading-xs">Kenali fitur PAGORA</p>
                    <ul className="mt-9 grid grid-cols-5 gap-6">
                        {group.items.map((item) => (
                            <li key={item.label}>
                                <a href={item.href} onClick={onClose} className="group/item block rounded-small">
                                    <span className="flex items-center gap-2 type-body-xs text-warm-60">
                                        <item.icon className="size-4" />
                                        {item.role}
                                    </span>
                                    <span className="mt-3 block text-[22px] leading-[26px] font-medium tracking-[-0.02em] underline-offset-4 group-hover/item:underline">
                                        {item.label}
                                    </span>
                                    <span className="mt-2 block type-body-xs text-warm-60">{item.description}</span>
                                </a>
                            </li>
                        ))}
                    </ul>
                    <a
                        href="#alur"
                        onClick={onClose}
                        className="group/more mt-9 flex items-center justify-between border-t border-warm-80 pt-5 type-body-xs font-medium"
                    >
                        Lihat bagaimana semua fitur terhubung dalam alur kerja
                        <ArrowRight className="size-4 transition-transform duration-300 group-hover/more:translate-x-1" />
                    </a>
                </div>
            </div>
        </div>
    );
}

function MobileMenu({ isOpen, onClose, isAuthenticated }) {
    const [expandedGroup, setExpandedGroup] = useState(null);

    return (
        <div
            id="mobile-menu"
            data-lenis-prevent
            className={cn(
                'fixed inset-x-0 top-[66px] bottom-0 z-30 flex flex-col overflow-y-auto bg-brand-offwhite transition-[opacity,visibility] duration-300 desk:hidden',
                isOpen ? 'visible opacity-100' : 'invisible opacity-0',
            )}
        >
            <ul className="landing-container flex flex-col border-t border-warm-80 py-4">
                {navigation.map((entry) => (
                    <li key={entry.label} className="border-b border-warm-80">
                        {entry.items ? (
                            <>
                                <button
                                    type="button"
                                    aria-expanded={expandedGroup === entry.label}
                                    onClick={() => setExpandedGroup((current) => (current === entry.label ? null : entry.label))}
                                    className="flex w-full items-center justify-between py-4 type-heading-xs"
                                >
                                    {entry.label}
                                    <ChevronDown
                                        className={cn('size-5 transition-transform duration-300', expandedGroup === entry.label && 'rotate-180')}
                                    />
                                </button>
                                <div
                                    className={cn(
                                        'grid transition-[grid-template-rows] duration-300 ease-standard',
                                        expandedGroup === entry.label ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]',
                                    )}
                                >
                                    <ul className="flex flex-col gap-1 overflow-hidden">
                                        {entry.items.map((item) => (
                                            <li key={item.label}>
                                                <a href={item.href} onClick={onClose} className="flex items-center gap-3 py-2 type-body-m">
                                                    <item.icon className="size-4 text-warm-60" />
                                                    {item.label}
                                                </a>
                                            </li>
                                        ))}
                                        <li className="h-3" aria-hidden="true" />
                                    </ul>
                                </div>
                            </>
                        ) : (
                            <a href={entry.href} onClick={onClose} className="block py-4 type-heading-xs">
                                {entry.label}
                            </a>
                        )}
                    </li>
                ))}
            </ul>
            <AuthActions
                isAuthenticated={isAuthenticated}
                size="responsive"
                className="landing-container mt-auto flex flex-col gap-3 pb-8 tab:flex-row"
            />
        </div>
    );
}

/**
 * Fixed navigation bar (DESIGN.md §5.3): solid at the top of the page,
 * a floating capsule once the page is scrolled.
 */
export default function LandingNavbar({ isAuthenticated, appName, logoUrl }) {
    const [isTop, setIsTop] = useState(true);
    const [openGroup, setOpenGroup] = useState(null);
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
    const closeTimer = useRef(null);
    const navRef = useRef(null);
    const { setLocked } = useSmoothScroll();

    useEffect(() => {
        const onScroll = () => setIsTop(window.scrollY === 0);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    useEffect(() => {
        const desktop = window.matchMedia(DESKTOP_QUERY);
        const onChange = () => desktop.matches && setIsMobileMenuOpen(false);
        desktop.addEventListener('change', onChange);

        return () => desktop.removeEventListener('change', onChange);
    }, []);

    useEffect(() => {
        setLocked(isMobileMenuOpen);

        return () => setLocked(false);
    }, [isMobileMenuOpen, setLocked]);

    useEffect(() => {
        const onPointerDown = (event) => {
            if (!navRef.current?.contains(event.target)) {
                setOpenGroup(null);
            }
        };
        document.addEventListener('pointerdown', onPointerDown);

        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            clearTimeout(closeTimer.current);
        };
    }, []);

    const openDropdown = (label) => {
        clearTimeout(closeTimer.current);
        setOpenGroup(label);
    };

    const closeDropdown = () => {
        clearTimeout(closeTimer.current);
        closeTimer.current = setTimeout(() => setOpenGroup(null), DROPDOWN_CLOSE_DELAY_MS);
    };

    const isSolid = isTop || isMobileMenuOpen;

    return (
        <header
            className={cn(
                'fixed inset-x-0 top-0 z-40 h-[66px] transition-[background-color] duration-300 desk:h-20',
                isSolid ? 'bg-brand-offwhite' : 'bg-transparent',
            )}
        >
            <div className="landing-container flex h-full items-center">
                <nav
                    ref={navRef}
                    aria-label="Navigasi utama"
                    className={cn(
                        'relative flex w-full items-center justify-between gap-6 rounded-full transition-[padding,margin,background-color,box-shadow] duration-300 ease-standard',
                        !isSolid &&
                            'mt-2 bg-white/85 py-2 pr-2 pl-4 shadow-soft-popover backdrop-blur-[8px]',
                    )}
                >
                    <Link href="/" className="flex shrink-0 items-center gap-2 rounded-xsmall">
                        <img src={logoUrl} alt="" className="size-8 object-contain" />
                        <span className="text-xl font-medium tracking-[-0.03em]">{appName}</span>
                    </Link>

                    <ul className="hidden items-center gap-1 desk:flex">
                        {navigation.map((entry) => (
                            <li key={entry.label}>
                                {entry.items ? (
                                    <DesktopDropdown
                                        group={entry}
                                        isOpen={openGroup === entry.label}
                                        onOpen={() => openDropdown(entry.label)}
                                        onClose={closeDropdown}
                                        onToggle={() => setOpenGroup((current) => (current === entry.label ? null : entry.label))}
                                    />
                                ) : (
                                    <a
                                        href={entry.href}
                                        className="block rounded-full px-3 py-2 text-[15px] font-medium transition-colors duration-200 hover:text-warm-60"
                                    >
                                        {entry.label}
                                    </a>
                                )}
                            </li>
                        ))}
                    </ul>

                    <AuthActions isAuthenticated={isAuthenticated} className="hidden items-center gap-2 desk:flex" />

                    <button
                        type="button"
                        aria-label={isMobileMenuOpen ? 'Tutup menu' : 'Buka menu'}
                        aria-expanded={isMobileMenuOpen}
                        aria-controls="mobile-menu"
                        onClick={() => setIsMobileMenuOpen((current) => !current)}
                        className="grid size-10 place-items-center rounded-full border border-warm-80 bg-white desk:hidden"
                    >
                        {isMobileMenuOpen ? <X className="size-5" /> : <Menu className="size-5" />}
                    </button>
                </nav>
            </div>

            <div
                aria-hidden="true"
                onClick={() => setOpenGroup(null)}
                className={cn(
                    'fixed inset-0 -z-10 hidden bg-brand-offwhite/30 backdrop-blur-md transition-[opacity,visibility] duration-300 desk:block',
                    openGroup ? 'visible opacity-100' : 'invisible opacity-0',
                )}
            />

            <MobileMenu isOpen={isMobileMenuOpen} onClose={() => setIsMobileMenuOpen(false)} isAuthenticated={isAuthenticated} />
        </header>
    );
}
