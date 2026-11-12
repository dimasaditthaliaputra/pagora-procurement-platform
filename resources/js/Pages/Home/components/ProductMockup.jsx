import { createContext, useContext } from 'react';
import { Camera, Check, CircleCheck, FileText, Truck, X } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Illustrative product screens drawn with HTML so the landing page needs no screenshots.
 * Figures shown are sample values, not real data.
 */

const MockupVariantContext = createContext('window');

function MockupFrame({ title, badge, children, className }) {
    const isWindow = useContext(MockupVariantContext) === 'window';

    return (
        <div
            className={cn(
                'flex h-full w-full flex-col overflow-hidden rounded-small border border-warm-80 bg-white text-brand-offblack',
                isWindow && 'shadow-soft-md',
                className,
            )}
        >
            <div className="flex items-center justify-between gap-3 border-b border-warm-80 px-4 py-3">
                <div className="flex min-w-0 items-center gap-2">
                    {isWindow && (
                        <>
                            <span className="size-2 shrink-0 rounded-full bg-warm-80" />
                            <span className="size-2 shrink-0 rounded-full bg-warm-80" />
                            <span className="mr-2 size-2 shrink-0 rounded-full bg-warm-80" />
                        </>
                    )}
                    <span className="truncate type-body-xs font-medium">{title}</span>
                </div>
                {badge}
            </div>
            <div className="flex flex-1 flex-col gap-3 p-4">{children}</div>
        </div>
    );
}

function StatusPill({ tone = 'neutral', children }) {
    const tones = {
        neutral: 'bg-brand-offwhite text-warm-50',
        positive: 'bg-positive-soft text-positive',
        negative: 'bg-negative-soft text-negative',
        caution: 'bg-caution-soft text-warm-50',
        lime: 'bg-brand-lime text-brand-offblack',
    };

    return <span className={cn('shrink-0 rounded-full px-2.5 py-1 text-[11px] leading-none font-medium whitespace-nowrap', tones[tone])}>{children}</span>;
}

function PriceMockup() {
    const actual = '0,62 18,55 36,58 54,44 72,48 90,36 108,40 126,30';
    const forecast = '126,30 144,26 162,22 180,20';

    return (
        <MockupFrame title="Tren harga · Beras" badge={<StatusPill tone="lime">Harga Pagu</StatusPill>}>
            <div className="flex items-end justify-between gap-4">
                <div>
                    <p className="text-[11px] text-warm-60">Rekomendasi Harga Pagu</p>
                    <p className="text-2xl font-medium tracking-tight">Rp13.450/kg</p>
                </div>
                <p className="text-[11px] text-positive">Regresi linier · data BI</p>
            </div>
            <div className="relative min-h-[90px] flex-1">
                <svg
                    viewBox="0 0 180 80"
                    preserveAspectRatio="none"
                    className="absolute inset-0 size-full overflow-visible"
                    role="img"
                    aria-label="Grafik tren harga"
                >
                    <line x1="0" y1="18" x2="180" y2="18" stroke="#14140f" strokeDasharray="4 4" strokeWidth="1" vectorEffect="non-scaling-stroke" />
                    <polyline points={actual} fill="none" stroke="#14140f" strokeWidth="2" strokeLinejoin="round" vectorEffect="non-scaling-stroke" />
                    <polyline points={forecast} fill="none" stroke="#6e6e64" strokeWidth="2" strokeDasharray="3 3" vectorEffect="non-scaling-stroke" />
                </svg>
            </div>
            <div className="grid grid-cols-3 gap-2 text-[11px]">
                {[
                    ['Terendah', 'Rp12.100'],
                    ['Rata-rata', 'Rp12.780'],
                    ['Estimasi', 'Rp13.020'],
                ].map(([label, value]) => (
                    <div key={label} className="rounded-xsmall bg-brand-offwhite px-2 py-1.5">
                        <p className="text-warm-60">{label}</p>
                        <p className="font-medium">{value}</p>
                    </div>
                ))}
            </div>
        </MockupFrame>
    );
}

function NegotiationMockup() {
    return (
        <MockupFrame title="Negosiasi PO" badge={<StatusPill tone="caution">Negosiasi</StatusPill>}>
            <div className="flex flex-col gap-2 text-[12px]">
                <div className="max-w-[80%] self-start rounded-small rounded-bl-[4px] bg-brand-offwhite px-3 py-2">
                    Gula Pasir 2 ton, kami tawarkan Rp17.400/kg.
                </div>
                <div className="max-w-[80%] self-end rounded-small rounded-br-[4px] bg-brand-offblack px-3 py-2 text-white">
                    Bisa di Rp16.900/kg untuk pengiriman minggu ini?
                </div>
                <div className="max-w-[80%] self-start rounded-small rounded-bl-[4px] bg-brand-offwhite px-3 py-2">
                    Sepakat di Rp17.000/kg.
                </div>
            </div>
            <div className="mt-auto flex flex-col gap-2 border-t border-warm-80 pt-3 text-[12px]">
                {[
                    ['Gula Pasir', 'Disepakati', 'positive'],
                    ['Minyak Goreng', 'Ditawar Ulang', 'caution'],
                    ['Bawang Merah', 'Dikeluarkan', 'neutral'],
                ].map(([item, status, tone]) => (
                    <div key={item} className="flex items-center justify-between">
                        <span>{item}</span>
                        <StatusPill tone={tone}>{status}</StatusPill>
                    </div>
                ))}
            </div>
        </MockupFrame>
    );
}

function ShippingMockup() {
    const steps = [
        ['Disepakati', true],
        ['Dalam Pengiriman', true],
        ['Diterima Gudang', false],
    ];

    return (
        <MockupFrame title="Form pengiriman" badge={<StatusPill tone="lime">Dalam Pengiriman</StatusPill>}>
            <div className="flex items-center gap-3 rounded-xsmall bg-brand-offwhite p-3">
                <span className="grid size-9 place-items-center rounded-full bg-brand-offblack text-brand-lime">
                    <Truck className="size-4" />
                </span>
                <div className="text-[12px]">
                    <p className="font-medium">B 9124 KXT · Truk engkel</p>
                    <p className="text-warm-60">Perkiraan tiba: Kamis, 14.00</p>
                </div>
            </div>
            <ol className="flex flex-col gap-3 text-[12px]">
                {steps.map(([label, done]) => (
                    <li key={label} className="flex items-center gap-3">
                        <span
                            className={cn(
                                'grid size-5 place-items-center rounded-full border',
                                done ? 'border-brand-offblack bg-brand-offblack text-white' : 'border-warm-80',
                            )}
                        >
                            {done && <Check className="size-3" />}
                        </span>
                        <span className={cn(!done && 'text-warm-60')}>{label}</span>
                    </li>
                ))}
            </ol>
            <p className="mt-auto rounded-xsmall border border-warm-80 px-3 py-2 text-[11px] text-warm-60">
                Kuantitas terkunci sejak barang dikirim.
            </p>
        </MockupFrame>
    );
}

function InspectionMockup() {
    return (
        <MockupFrame title="Inspeksi QC · per item" badge={<StatusPill tone="negative">Diterima Sebagian</StatusPill>}>
            {[
                ['Beras', '500 kg', true],
                ['Telur Ayam', '120 kg', true],
                ['Cabai Merah', '80 kg', false],
            ].map(([item, quantity, accepted]) => (
                <div key={item} className="flex items-center justify-between gap-3 rounded-xsmall border border-warm-80 px-3 py-2 text-[12px]">
                    <div>
                        <p className="font-medium">{item}</p>
                        <p className="text-warm-60">{quantity}</p>
                    </div>
                    {accepted ? (
                        <span className="flex items-center gap-1 text-positive">
                            <CircleCheck className="size-4" /> Diterima
                        </span>
                    ) : (
                        <span className="flex items-center gap-1 text-negative">
                            <X className="size-4" /> Ditolak
                        </span>
                    )}
                </div>
            ))}
            <div className="mt-auto flex items-center gap-2 rounded-xsmall bg-negative-soft px-3 py-2 text-[11px] text-negative">
                <Camera className="size-4 shrink-0" />
                Foto bukti tersimpan, sengketa item dibuka otomatis.
            </div>
        </MockupFrame>
    );
}

function MatchingMockup() {
    const documents = [
        ['Purchase Order', 'Rp42.500.000'],
        ['Penerimaan Gudang', 'Rp42.500.000'],
        ['Invoice', 'Rp42.500.000'],
    ];

    return (
        <MockupFrame title="Verifikasi tagihan" badge={<StatusPill tone="positive">Siap Dibayar</StatusPill>}>
            {documents.map(([label, amount]) => (
                <div key={label} className="flex items-center justify-between gap-3 rounded-xsmall bg-brand-offwhite px-3 py-2 text-[12px]">
                    <span className="flex items-center gap-2">
                        <FileText className="size-4 text-warm-60" />
                        {label}
                    </span>
                    <span className="flex items-center gap-2 font-medium">
                        {amount}
                        <Check className="size-4 text-positive" />
                    </span>
                </div>
            ))}
            <div className="mt-auto flex items-center justify-between rounded-xsmall bg-brand-offblack px-3 py-2.5 text-[12px] text-white">
                <span>3 dari 3 dokumen cocok</span>
                <span className="rounded-full bg-brand-lime px-2.5 py-1 text-[11px] font-medium text-brand-offblack">Bayar</span>
            </div>
        </MockupFrame>
    );
}

const MOCKUPS = {
    price: PriceMockup,
    negotiation: NegotiationMockup,
    shipping: ShippingMockup,
    inspection: InspectionMockup,
    matching: MatchingMockup,
};

/**
 * @param {'window'|'card'} variant `window` draws window chrome; `card` is for screens inside a device frame.
 */
export default function ProductMockup({ type, variant = 'window', className }) {
    const Mockup = MOCKUPS[type];

    return (
        <MockupVariantContext.Provider value={variant}>
            <div className={cn('h-full w-full', className)}>
                <Mockup />
            </div>
        </MockupVariantContext.Provider>
    );
}
