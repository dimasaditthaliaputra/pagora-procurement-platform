import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { features } from '@/data/landingContent';
import { Stagger, StaggerItem } from './Reveal';
import VelocityMarquee from './VelocityMarquee';

const FOOTER_COLUMNS = [
    {
        title: 'Produk',
        links: features.map((feature) => ({ label: feature.tab, href: `#fitur-${feature.id}` })),
    },
    {
        title: 'Platform',
        links: [
            { label: 'Alur kerja', href: '#alur' },
            { label: 'Peran pengguna', href: '#peran' },
            { label: 'Keamanan data', href: '#keamanan' },
            { label: 'Mulai', href: '#mulai' },
        ],
    },
    {
        title: 'Akun',
        links: [
            { label: 'Masuk', href: '/login' },
            { label: 'Daftar perusahaan', href: '/register' },
            { label: 'Daftar vendor', href: '/register/vendor' },
            { label: 'Lupa kata sandi', href: '/forgot-password' },
        ],
    },
];

function FooterLink({ href, children }) {
    const className = 'type-body-s transition-colors duration-200 hover:text-warm-50';

    if (href.startsWith('#')) {
        return (
            <a href={href} className={className}>
                {children}
            </a>
        );
    }

    return (
        <Link href={href} className={className}>
            {children}
        </Link>
    );
}

function LinkList({ links }) {
    return (
        <ul className="flex flex-col gap-3">
            {links.map((link) => (
                <li key={link.label}>
                    <FooterLink href={link.href}>{link.label}</FooterLink>
                </li>
            ))}
        </ul>
    );
}

function MarqueeText() {
    return (
        <p
            className="shrink-0 pr-16 text-[72px] leading-none font-medium tracking-[-0.045em] whitespace-nowrap desk:pr-40 desk:text-[160px]"
        >
            Pengadaan yang terkendali
        </p>
    );
}

export default function LandingFooter({ appName, logoUrl, tagline }) {
    return (
        <footer className="overflow-hidden bg-brand-lime pt-14 text-brand-offblack desk:pt-20">
            <div className="landing-container grid gap-10 desk:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                <div>
                    <Link href="/" className="inline-flex items-center gap-2 rounded-xsmall">
                        <img src={logoUrl} alt="" className="size-8 object-contain" />
                        <span className="text-xl font-medium tracking-[-0.03em]">{appName}</span>
                    </Link>
                    <p className="mt-4 max-w-[320px] type-body-s text-brand-offblack/70">
                        {tagline || 'Portal e-procurement B2B untuk pengadaan bahan baku industri.'}
                    </p>
                </div>

                <Stagger as="div" className="hidden grid-cols-3 gap-8 desk:grid">
                    {FOOTER_COLUMNS.map((column) => (
                        <StaggerItem as="div" key={column.title}>
                            <p className="mb-4 type-body-xs font-medium text-brand-offblack/60">{column.title}</p>
                            <LinkList links={column.links} />
                        </StaggerItem>
                    ))}
                </Stagger>

                <div className="flex flex-col border-t border-brand-offblack/20 desk:hidden">
                    {FOOTER_COLUMNS.map((column) => (
                        <details key={column.title} className="group/details border-b border-brand-offblack/20">
                            <summary className="flex cursor-pointer list-none items-center justify-between py-4 type-body-m font-medium [&::-webkit-details-marker]:hidden">
                                {column.title}
                                <ChevronDown className="size-5 transition-transform duration-300 group-open/details:rotate-180" />
                            </summary>
                            <div className="pb-5">
                                <LinkList links={column.links} />
                            </div>
                        </details>
                    ))}
                </div>
            </div>

            <div className="mt-14 desk:mt-20" aria-hidden="true">
                <VelocityMarquee baseVelocity={-3} maxSkew={8}>
                    <MarqueeText />
                </VelocityMarquee>
            </div>

            <div className="landing-container mt-10 flex flex-col gap-2 border-t border-brand-offblack/20 py-6 type-body-xs tab:flex-row tab:justify-between">
                <p>
                    © {new Date().getFullYear()} {appName}. Seluruh hak cipta dilindungi.
                </p>
                <p className="text-brand-offblack/70">Data harga acuan bersumber dari Bank Indonesia.</p>
            </div>
        </footer>
    );
}
