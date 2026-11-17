import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowUpRight,
    BookOpen,
    CheckCircle2,
    ChevronDown,
    FileText,
    Mail,
    Scale,
    ShieldCheck,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import BundledLogo from '../../../assets/logo/logo.webp';
import LandingFooter from './components/LandingFooter';
import LandingNavbar from './components/LandingNavbar';
import SmoothScrollProvider from './components/SmoothScrollProvider';

const DEFAULT_META = {
    title: 'Syarat dan Ketentuan Penggunaan PAGORA',
    version: '1.0',
    effective_date: '1 November 2026',
    language: 'id',
    operator_name: 'PAGORA',
    contact_email: 'support@pagora.id',
    jurisdiction: 'Pengadilan Negeri di wilayah hukum Republik Indonesia',
};

const DEFAULT_ACCEPTANCE = {
    checkbox_label: 'Saya telah membaca dan menyetujui Syarat dan Ketentuan PAGORA.',
    applies_to: [
        'Registrasi Tenant (UC-01)',
        'Registrasi Vendor (UC-02)',
        'Daftar dengan Google',
        'Penerimaan ajakan perusahaan (UC-03), bila pengguna belum pernah menyetujui versi terbaru',
    ],
};

export default function SyaratKetentuan({ meta: metaProp, sections: sectionsProp, acceptance: acceptanceProp }) {
    const { auth, aplikasi } = usePage().props;
    const isAuthenticated = Boolean(auth?.user);
    const appName = aplikasi?.nama || 'PAGORA';
    const logoUrl = aplikasi?.logo_url || BundledLogo;

    const meta = useMemo(() => ({ ...DEFAULT_META, ...(metaProp || {}) }), [metaProp]);
    const sections = useMemo(() => (Array.isArray(sectionsProp) ? sectionsProp : []), [sectionsProp]);
    const acceptance = useMemo(
        () => ({ ...DEFAULT_ACCEPTANCE, ...(acceptanceProp || {}) }),
        [acceptanceProp]
    );

    const [activeSectionId, setActiveSectionId] = useState(() => sections[0]?.id || 'definisi');
    const [mobileTocOpen, setMobileTocOpen] = useState(false);
    const mobileTocRef = useRef(null);

    const activeSection = useMemo(
        () => sections.find((s) => s.id === activeSectionId) || sections[0] || null,
        [sections, activeSectionId]
    );

    // Track currently visible section using IntersectionObserver
    useEffect(() => {
        if (!sections.length || typeof IntersectionObserver === 'undefined') {
            return undefined;
        }

        const elements = sections
            .map((section) => document.getElementById(`pasal-${section.id}`))
            .filter(Boolean);

        if (!elements.length) {
            return undefined;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                const visible = entries
                    .filter((entry) => entry.isIntersecting)
                    .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);

                if (visible.length > 0) {
                    const id = visible[0].target.getAttribute('data-section-id');
                    if (id) {
                        setActiveSectionId(id);
                    }
                }
            },
            {
                rootMargin: '-120px 0px -65% 0px',
                threshold: [0, 0.25, 0.5, 1],
            }
        );

        elements.forEach((el) => observer.observe(el));

        return () => observer.disconnect();
    }, [sections]);

    // Close mobile TOC dropdown on outside click or Escape
    useEffect(() => {
        if (!mobileTocOpen) {
            return undefined;
        }

        const handlePointerDown = (event) => {
            if (!mobileTocRef.current?.contains(event.target)) {
                setMobileTocOpen(false);
            }
        };

        const handleKeyDown = (event) => {
            if (event.key === 'Escape') {
                setMobileTocOpen(false);
            }
        };

        document.addEventListener('pointerdown', handlePointerDown);
        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('pointerdown', handlePointerDown);
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [mobileTocOpen]);

    const scrollToSection = (sectionId) => {
        setActiveSectionId(sectionId);
        setMobileTocOpen(false);

        const target = document.getElementById(`pasal-${sectionId}`);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (window.history?.replaceState) {
                window.history.replaceState(null, '', `#pasal-${sectionId}`);
            }
        }
    };

    return (
        <>
            <Head title={`${meta.title} — ${appName}`} />

            <SmoothScrollProvider>
                <div className="landing-page min-h-screen flex flex-col">
                    <a
                        href="#isi-syarat-ketentuan"
                        className="sr-only rounded-full bg-brand-offblack px-4 py-2 text-white focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50"
                    >
                        Lewati ke isi pasal
                    </a>

                    <LandingNavbar
                        isAuthenticated={isAuthenticated}
                        appName={appName}
                        logoUrl={logoUrl}
                    />

                    {/* Bagian Atas: Judul, Versi, dan Metadata S&K */}
                    <header className="pt-24 pb-10 desk:pt-32 desk:pb-14 border-b border-warm-80 bg-brand-offwhite">
                        <div className="landing-container">
                            <div className="flex flex-wrap items-center justify-between gap-3 mb-6">
                                <Link
                                    href="/"
                                    className="inline-flex items-center gap-2 rounded-full border border-warm-80 bg-white px-3.5 py-1.5 type-body-xs font-medium text-brand-offblack transition-colors hover:bg-warm-95"
                                >
                                    <ArrowLeft className="size-4" />
                                    <span>Kembali ke Beranda</span>
                                </Link>

                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="inline-flex items-center gap-1.5 rounded-full bg-brand-lime px-3.5 py-1 type-body-xs font-medium text-brand-offblack">
                                        <FileText className="size-3.5" />
                                        <span>Versi {meta.version}</span>
                                    </span>
                                    <span className="inline-flex items-center rounded-full border border-warm-80 bg-white px-3.5 py-1 type-body-xs text-warm-50">
                                        Berlaku sejak: {meta.effective_date}
                                    </span>
                                </div>
                            </div>

                            <div className="max-w-4xl">
                                <p className="type-eyebrow text-warm-60 mb-3">
                                    Dokumen Hukum &amp; Ketentuan Penggunaan Platform
                                </p>
                                <h1 className="type-heading-m text-brand-offblack">
                                    {meta.title}
                                </h1>
                                <p className="mt-4 type-body-l text-warm-50 max-w-3xl">
                                    Dengan mendaftar atau menggunakan {appName}, Anda menyetujui Syarat
                                    dan Ketentuan (S&amp;K) berikut.
                                </p>
                            </div>

                            {/* Ringkasan Identitas Dokumen */}
                            <div className="mt-8 grid grid-cols-1 gap-3 tab:grid-cols-3 rounded-small border border-warm-80 bg-white p-4 desk:p-5 shadow-soft-xs">
                                <div className="flex items-start gap-3">
                                    <div className="grid size-9 shrink-0 place-items-center rounded-xsmall bg-brand-lime/40 text-brand-offblack">
                                        <ShieldCheck className="size-4" />
                                    </div>
                                    <div className="min-w-0">
                                        <p className="type-body-xs text-warm-60">Penyelenggara</p>
                                        <p className="type-body-s font-medium text-brand-offblack truncate">
                                            {meta.operator_name}
                                        </p>
                                    </div>
                                </div>

                                <div className="flex items-start gap-3">
                                    <div className="grid size-9 shrink-0 place-items-center rounded-xsmall bg-brand-cyan/40 text-brand-offblack">
                                        <Mail className="size-4" />
                                    </div>
                                    <div className="min-w-0">
                                        <p className="type-body-xs text-warm-60">Kontak Resmi</p>
                                        <a
                                            href={`mailto:${meta.contact_email}`}
                                            className="type-body-s font-medium text-brand-offblack underline underline-offset-4 hover:text-warm-50 truncate block"
                                        >
                                            {meta.contact_email}
                                        </a>
                                    </div>
                                </div>

                                <div className="flex items-start gap-3">
                                    <div className="grid size-9 shrink-0 place-items-center rounded-xsmall bg-brand-purple/40 text-brand-offblack">
                                        <Scale className="size-4" />
                                    </div>
                                    <div className="min-w-0">
                                        <p className="type-body-xs text-warm-60">Jumlah Ketentuan</p>
                                        <p className="type-body-s font-medium text-brand-offblack">
                                            {sections.length} Pasal Utama
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </header>

                    {/* Daftar Isi Mobile & Tablet (Dropdown Sticky) */}
                    <div
                        ref={mobileTocRef}
                        className="sticky top-[66px] z-20 border-b border-warm-80 bg-warm-95/95 backdrop-blur-md desk:hidden"
                    >
                        <div className="landing-container py-3 relative">
                            <label htmlFor="mobile-toc-trigger" className="sr-only">
                                Pilih Pasal Daftar Isi
                            </label>
                            <button
                                id="mobile-toc-trigger"
                                type="button"
                                aria-expanded={mobileTocOpen}
                                aria-controls="mobile-toc-listbox"
                                onClick={() => setMobileTocOpen((prev) => !prev)}
                                className="flex w-full items-center justify-between gap-3 rounded-small border border-warm-80 bg-white px-4 py-2.5 text-left shadow-soft-xs transition-colors hover:bg-warm-95"
                            >
                                <div className="flex items-center gap-2.5 min-w-0">
                                    <span className="inline-flex items-center justify-center rounded-xsmall bg-brand-lime px-2 py-0.5 text-xs font-semibold text-brand-offblack shrink-0">
                                        Daftar Isi
                                    </span>
                                    <span className="type-body-xs font-medium text-brand-offblack truncate">
                                        {activeSection
                                            ? `${activeSection.number}. ${activeSection.title}`
                                            : 'Pilih Pasal'}
                                    </span>
                                </div>
                                <ChevronDown
                                    className={cn(
                                        'size-4 shrink-0 text-warm-50 transition-transform duration-200',
                                        mobileTocOpen && 'rotate-180'
                                    )}
                                />
                            </button>

                            {mobileTocOpen && (
                                <div
                                    id="mobile-toc-listbox"
                                    role="region"
                                    aria-label="Daftar Isi Syarat dan Ketentuan"
                                    data-lenis-prevent
                                    className="mt-2 max-h-[60vh] overflow-y-auto rounded-small border border-warm-80 bg-white p-2 shadow-soft-popover"
                                >
                                    <ul className="space-y-1">
                                        {sections.map((section) => {
                                            const isCurrent = section.id === activeSectionId;
                                            return (
                                                <li key={section.id}>
                                                    <button
                                                        type="button"
                                                        onClick={() => scrollToSection(section.id)}
                                                        className={cn(
                                                            'flex w-full items-center gap-2.5 rounded-xsmall px-3 py-2 text-left type-body-xs transition-colors',
                                                            isCurrent
                                                                ? 'bg-brand-offblack font-medium text-white'
                                                                : 'text-warm-50 hover:bg-warm-95 hover:text-brand-offblack'
                                                        )}
                                                    >
                                                        <span
                                                            className={cn(
                                                                'inline-flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-medium',
                                                                isCurrent
                                                                    ? 'bg-brand-lime text-brand-offblack'
                                                                    : 'bg-brand-offwhite text-warm-50'
                                                            )}
                                                        >
                                                            {section.number}
                                                        </span>
                                                        <span className="truncate">{section.title}</span>
                                                    </button>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Area Konten Utama: Daftar Isi di Samping (Desktop) & Isi Pasal di Tengah */}
                    <main id="isi-syarat-ketentuan" className="flex-1 py-10 desk:py-16">
                        <div className="landing-container grid grid-cols-1 gap-10 desk:grid-cols-[280px_minmax(0,1fr)] wide:grid-cols-[310px_minmax(0,820px)_1fr]">
                            {/* Sidebar Daftar Isi (Desktop) */}
                            <aside
                                aria-label="Daftar Isi Syarat dan Ketentuan"
                                className="hidden desk:block"
                            >
                                <div
                                    data-lenis-prevent
                                    className="sticky top-24 max-h-[calc(100vh-7rem)] overflow-y-auto rounded-small border border-warm-80 bg-white p-4 shadow-soft-xs"
                                >
                                    <div className="flex items-center justify-between gap-2 pb-3 mb-3 border-b border-warm-80">
                                        <span className="inline-flex items-center gap-2 type-body-xs font-medium text-brand-offblack">
                                            <BookOpen className="size-4 text-warm-60" />
                                            <span>Daftar Isi</span>
                                        </span>
                                        <span className="rounded-full bg-brand-offwhite px-2.5 py-0.5 text-xs font-medium text-warm-50">
                                            {sections.length} Pasal
                                        </span>
                                    </div>

                                    <nav aria-label="Navigasi Pasal">
                                        <ul className="space-y-1">
                                            {sections.map((section) => {
                                                const isCurrent = section.id === activeSectionId;
                                                return (
                                                    <li key={section.id}>
                                                        <a
                                                            href={`#pasal-${section.id}`}
                                                            onClick={(e) => {
                                                                e.preventDefault();
                                                                scrollToSection(section.id);
                                                            }}
                                                            className={cn(
                                                                'group flex items-start gap-2.5 rounded-xsmall px-3 py-2 type-body-xs transition-colors',
                                                                isCurrent
                                                                    ? 'bg-brand-offblack font-medium text-white'
                                                                    : 'text-warm-50 hover:bg-brand-offwhite hover:text-brand-offblack'
                                                            )}
                                                        >
                                                            <span
                                                                className={cn(
                                                                    'mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-medium transition-colors',
                                                                    isCurrent
                                                                        ? 'bg-brand-lime text-brand-offblack'
                                                                        : 'bg-brand-offwhite text-warm-60 group-hover:bg-warm-80/60 group-hover:text-brand-offblack'
                                                                )}
                                                            >
                                                                {section.number}
                                                            </span>
                                                            <span className="leading-snug">
                                                                {section.title}
                                                            </span>
                                                        </a>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    </nav>
                                </div>
                            </aside>

                            {/* Kolom Tengah: Isi Pasal demi Pasal */}
                            <article className="min-w-0 space-y-6">
                                {sections.map((section) => (
                                    <section
                                        key={section.id}
                                        id={`pasal-${section.id}`}
                                        data-section-id={section.id}
                                        className="scroll-mt-32 desk:scroll-mt-28 rounded-small border border-warm-80 bg-white p-6 desk:p-8 shadow-soft-xs transition-shadow hover:shadow-soft-sm"
                                    >
                                        <div className="flex flex-wrap items-center gap-2.5 pb-4 mb-5 border-b border-warm-80">
                                            <span className="inline-flex items-center rounded-full bg-brand-lime/70 px-3 py-1 text-xs font-semibold text-brand-offblack">
                                                Pasal {section.number}
                                            </span>
                                            <h2 className="type-heading-xs text-brand-offblack">
                                                {section.number}. {section.title}
                                            </h2>
                                        </div>

                                        <ol className="space-y-4">
                                            {(section.clauses || []).map((clause, index) => {
                                                const clauseNumber = `${section.number}.${index + 1}`;
                                                return (
                                                    <li
                                                        key={clauseNumber}
                                                        className="grid grid-cols-[44px_minmax(0,1fr)] gap-3 items-baseline"
                                                    >
                                                        <span className="font-mono text-xs font-semibold text-warm-60 select-all">
                                                            {clauseNumber}
                                                        </span>
                                                        <p className="type-body-s leading-relaxed text-brand-offblack/90">
                                                            {clause}
                                                        </p>
                                                    </li>
                                                );
                                            })}
                                        </ol>
                                    </section>
                                ))}

                                {/* Kartu Pernyataan Persetujuan & Tautan Pendaftaran */}
                                <section
                                    aria-labelledby="persetujuan-heading"
                                    className="rounded-medium border-2 border-brand-offblack bg-brand-offwhite p-6 desk:p-8 shadow-soft-sm"
                                >
                                    <div className="flex items-start gap-3.5">
                                        <div className="grid size-10 shrink-0 place-items-center rounded-full bg-brand-lime text-brand-offblack">
                                            <CheckCircle2 className="size-5" />
                                        </div>
                                        <div className="space-y-2">
                                            <p
                                                id="persetujuan-heading"
                                                className="type-eyebrow text-warm-60"
                                            >
                                                Pernyataan Persetujuan Pengguna
                                            </p>
                                            <p className="type-heading-xs text-brand-offblack">
                                                &ldquo;{acceptance.checkbox_label}&rdquo;
                                            </p>
                                            <p className="type-body-xs text-warm-50">
                                                Ketentuan ini berlaku dan mengikat secara sah pada seluruh alur
                                                penggunaan platform {appName}, mencakup:
                                            </p>
                                        </div>
                                    </div>

                                    {Array.isArray(acceptance.applies_to) &&
                                        acceptance.applies_to.length > 0 && (
                                            <ul className="mt-5 grid grid-cols-1 gap-2.5 tab:grid-cols-2">
                                                {acceptance.applies_to.map((item) => (
                                                    <li
                                                        key={item}
                                                        className="flex items-center gap-2.5 rounded-xsmall border border-warm-80 bg-white px-3.5 py-2.5 type-body-xs text-brand-offblack"
                                                    >
                                                        <span className="size-2 rounded-full bg-brand-offblack shrink-0" />
                                                        <span>{item}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}

                                    <div className="mt-6 pt-5 border-t border-warm-80 flex flex-wrap items-center justify-between gap-4">
                                        <div className="type-body-xs text-warm-50">
                                            Pertanyaan terkait S&amp;K? Hubungi{' '}
                                            <a
                                                href={`mailto:${meta.contact_email}`}
                                                className="font-medium text-brand-offblack underline underline-offset-4"
                                            >
                                                {meta.contact_email}
                                            </a>
                                        </div>

                                        {!isAuthenticated && (
                                            <div className="flex flex-wrap items-center gap-2.5">
                                                <Link
                                                    href="/register"
                                                    className="inline-flex items-center gap-1.5 rounded-full bg-brand-offblack px-4 py-2 type-body-xs font-medium text-white transition-colors hover:bg-warm-50"
                                                >
                                                    <span>Daftar Perusahaan (Tenant)</span>
                                                    <ArrowUpRight className="size-4" />
                                                </Link>
                                                <Link
                                                    href="/register/vendor"
                                                    className="inline-flex items-center gap-1.5 rounded-full border border-brand-offblack bg-white px-4 py-2 type-body-xs font-medium text-brand-offblack transition-colors hover:bg-brand-lime"
                                                >
                                                    <span>Daftar sebagai Vendor</span>
                                                    <ArrowUpRight className="size-4" />
                                                </Link>
                                            </div>
                                        )}
                                    </div>
                                </section>
                            </article>
                        </div>
                    </main>

                    <LandingFooter
                        appName={appName}
                        logoUrl={logoUrl}
                        tagline={aplikasi?.tagline}
                    />
                </div>
            </SmoothScrollProvider>
        </>
    );
}
