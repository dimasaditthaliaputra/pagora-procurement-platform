import { Building2, CheckCircle2, Info, MinusCircle, TrendingDown, TrendingUp, UserRound } from 'lucide-react';
import { cn } from '@/lib/utils';
import { formatRupiah, formatTime } from '@/lib/format';

function SystemPill({ icon: Icon, tone = 'default', children }) {
    return (
        <div className="flex justify-center">
            <span
                className={cn(
                    'inline-flex max-w-[90%] items-start gap-1.5 rounded-2xl border px-3 py-1.5 text-xs',
                    tone === 'success' && 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400',
                    tone === 'danger' && 'border-destructive/40 bg-destructive/10 text-destructive',
                    tone === 'default' && 'bg-muted text-muted-foreground'
                )}
            >
                <Icon className="mt-0.5 size-3 shrink-0" />
                <span>{children}</span>
            </span>
        </div>
    );
}

// Tenant-only guidance: how far an offer is from Harga Pagu (BR-02: `ceiling` is never sent to Vendors).
function CeilingGap({ amount, ceiling }) {
    if (!ceiling) return null;
    const percent = ((amount - ceiling) / ceiling) * 100;
    const above = percent > 0;
    const Icon = above ? TrendingUp : TrendingDown;

    return (
        <span className={cn('inline-flex items-center gap-1 text-xs', above ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400')}>
            <Icon className="size-3" />
            {Math.abs(percent).toFixed(1)}% {above ? 'di atas' : 'di bawah'} Harga Pagu
        </span>
    );
}

export function NegotiationMessage({ message, ceilingByItem = {} }) {
    const { sender, item } = message;
    const time = message.pending ? 'Mengirim...' : formatTime(message.created_at);
    const unit = item?.unit ? `/${item.unit}` : '';

    if (message.type === 'system') {
        const tone = message.message?.startsWith('Semua item disepakati') ? 'success' : message.message?.includes('dibatalkan') ? 'danger' : 'default';
        return <SystemPill icon={tone === 'success' ? CheckCircle2 : Info} tone={tone}>{message.message} · {time}</SystemPill>;
    }

    if (message.type === 'acceptance') {
        return (
            <SystemPill icon={CheckCircle2} tone="success">
                {sender.name} menyetujui {formatRupiah(message.offer_amount)}{unit} untuk {item?.name} · {time}
            </SystemPill>
        );
    }

    if (message.type === 'item_removed') {
        return (
            <SystemPill icon={MinusCircle} tone="danger">
                {item?.name} dikeluarkan dari PO{message.message ? ` · ${message.message}` : ''} · {time}
            </SystemPill>
        );
    }

    const mine = sender.is_me;
    const vendor = sender.side === 'vendor';
    const Icon = vendor ? Building2 : UserRound;
    const isOffer = message.type === 'offer';

    return (
        <div className={cn('flex max-w-[85%] gap-2.5', mine ? 'ml-auto flex-row-reverse' : 'mr-auto', message.pending && 'opacity-60')}>
            <div
                className={cn(
                    'flex size-8 shrink-0 items-center justify-center rounded-full border',
                    vendor ? 'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-400' : 'bg-primary/10 text-primary'
                )}
                aria-hidden="true"
            >
                <Icon className="size-4" />
            </div>
            <div className={cn('flex min-w-0 flex-col gap-1', mine && 'items-end')}>
                <span className="text-xs text-muted-foreground">
                    {sender.name}
                    {sender.role_label ? ` (${sender.role_label})` : ''} · {time}
                </span>
                <div
                    className={cn(
                        'space-y-1 rounded-2xl border px-4 py-2.5',
                        mine ? 'rounded-tr-sm border-primary bg-primary text-primary-foreground' : 'rounded-tl-sm bg-card'
                    )}
                >
                    {isOffer && (
                        <div>
                            <p className={cn('text-[11px] uppercase tracking-wide', mine ? 'text-primary-foreground/70' : 'text-muted-foreground')}>
                                {vendor ? 'Tawaran Vendor' : 'Harga diajukan'} · {item?.name}
                            </p>
                            <p className="text-lg font-bold tabular-nums">
                                {formatRupiah(message.offer_amount)}
                                <span className="text-sm font-medium">{unit}</span>
                            </p>
                        </div>
                    )}
                    {message.message && <p className="whitespace-pre-line break-words text-sm">{message.message}</p>}
                </div>
                {isOffer && <CeilingGap amount={message.offer_amount} ceiling={ceilingByItem[item?.uuid]} />}
            </div>
        </div>
    );
}
