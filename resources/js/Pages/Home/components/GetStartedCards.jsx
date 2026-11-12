import { Building2, LayoutDashboard, Store } from 'lucide-react';
import { cn } from '@/lib/utils';
import LandingButton from './LandingButton';
import LandingImage from './LandingImage';
import { Stagger, StaggerItem } from './Reveal';

const GUEST_CARDS = [
    {
        title: 'Untuk perusahaan pembeli',
        description: 'Daftarkan perusahaan, undang tim internal, dan mulai mengelola pengadaan bahan baku.',
        action: { label: 'Daftar perusahaan', href: '/register' },
        icon: Building2,
        image: 'mulai-perusahaan',
        color: 'bg-brand-lime',
    },
    {
        title: 'Untuk vendor',
        description: 'Daftar mandiri atau lewat undangan perusahaan, lalu tanggapi PO langsung dari portal.',
        action: { label: 'Daftar sebagai vendor', href: '/register/vendor' },
        icon: Store,
        image: 'mulai-vendor',
        color: 'bg-brand-cyan',
    },
    {
        title: 'Sudah punya akun?',
        description: 'Masuk dengan email atau akun Google untuk melanjutkan pekerjaan Anda.',
        action: { label: 'Masuk', href: '/login' },
        icon: LayoutDashboard,
        image: 'mulai-akun',
        color: 'bg-brand-pink',
    },
];

const DASHBOARD_CARD = {
    ...GUEST_CARDS[2],
    title: 'Lanjutkan pekerjaan Anda',
    description: 'Anda sudah masuk. Buka dasbor untuk melihat PO, pengiriman, dan tagihan terbaru.',
    action: { label: 'Buka dasbor', href: '/dashboard' },
};

export default function GetStartedCards({ isAuthenticated }) {
    const cards = isAuthenticated ? [GUEST_CARDS[0], GUEST_CARDS[1], DASHBOARD_CARD] : GUEST_CARDS;

    return (
        <Stagger className="landing-container mt-10 grid gap-4 desk:mt-14 desk:grid-cols-3 desk:gap-5">
            {cards.map((card) => (
                <StaggerItem
                    key={card.title}
                    whileHover={{ y: -6 }}
                    transition={{ type: 'spring', stiffness: 300, damping: 24 }}
                    className="group/card flex flex-col overflow-hidden rounded-small bg-white shadow-soft-sm transition-shadow duration-500 hover:shadow-soft-md desk:rounded-medium"
                >
                    <div className="flex flex-col p-6 desk:p-8">
                        <h3 className="type-heading-xs">{card.title}</h3>
                        <p className="mt-3 type-body-s text-warm-60">{card.description}</p>
                        <LandingButton href={card.action.href} variant="tertiary" className="mt-6 self-start">
                            {card.action.label}
                        </LandingButton>
                    </div>
                    <div className={cn('relative mx-3 mt-auto mb-3 grid h-[200px] place-items-center overflow-hidden rounded-xsmall desk:h-[220px]', card.color)} aria-hidden="true">
                        <card.icon className="size-10 text-brand-offblack/40" />
                        <LandingImage
                            name={card.image}
                            isDecorative
                            className="transition-transform duration-700 ease-out-quick group-hover/card:scale-[1.05]"
                        />
                        <span
                            className={cn(
                                'absolute bottom-3 left-3 grid size-12 place-items-center rounded-full text-brand-offblack shadow-soft-sm',
                                card.color,
                            )}
                        >
                            <card.icon className="size-5" />
                        </span>
                    </div>
                </StaggerItem>
            ))}
        </Stagger>
    );
}
