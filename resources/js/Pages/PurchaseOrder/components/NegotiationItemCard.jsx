import { Check, MinusCircle, Send } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';
import { formatNumber, formatRupiah } from '@/lib/format';

const STATUS_VARIANT = {
    pending: 'secondary',
    countered: 'warning',
    agreed: 'green',
    removed: 'destructive',
};

function PriceRow({ label, value, unit, emphasis = false, muted = false }) {
    return (
        <div className="flex items-baseline justify-between gap-3 text-xs">
            <span className="text-muted-foreground">{label}</span>
            <span className={cn('tabular-nums', emphasis && 'text-sm font-semibold text-foreground', muted && 'text-muted-foreground')}>
                {value !== null && value !== undefined ? `${formatRupiah(value)}/${unit}` : '-'}
            </span>
        </div>
    );
}

export function NegotiationItemCard({ item, isTenant, busy, onOffer, onAccept, onRemove }) {
    const removed = item.status === 'removed';
    const agreed = item.status === 'agreed';
    const hasActions = item.can.offer || item.can.accept || item.can.remove;
    const offerLabel = isTenant ? (item.proposed_price !== null ? 'Ajukan ulang' : 'Ajukan harga') : 'Ajukan harga lain';
    const acceptPrice = isTenant ? item.vendor_offer_price : item.proposed_price;

    return (
        <li className={cn('space-y-2.5 rounded-lg border p-3', removed && 'opacity-60', agreed && 'border-emerald-200 dark:border-emerald-900/60')}>
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className={cn('text-sm font-medium', removed && 'line-through')}>{item.name}</p>
                    <p className="text-xs text-muted-foreground">
                        {formatNumber(item.quantity)} {item.unit}
                    </p>
                </div>
                <Badge variant={STATUS_VARIANT[item.status] ?? 'secondary'} className="shrink-0">
                    {item.status_label}
                </Badge>
            </div>

            <div className="space-y-1">
                {isTenant && <PriceRow label="Estimasi anggaran" value={item.estimated_unit_price} unit={item.unit} muted />}
                {isTenant && item.ceiling_price !== undefined && <PriceRow label="Harga Pagu" value={item.ceiling_price} unit={item.unit} muted />}
                {agreed ? (
                    <PriceRow label="Harga disepakati" value={item.agreed_price} unit={item.unit} emphasis />
                ) : (
                    <>
                        <PriceRow label={isTenant ? 'Ajuan Anda' : 'Ajuan Purchasing'} value={item.proposed_price} unit={item.unit} emphasis={!isTenant} />
                        <PriceRow label={isTenant ? 'Tawaran Vendor' : 'Tawaran Anda'} value={item.vendor_offer_price} unit={item.unit} emphasis={isTenant} />
                    </>
                )}
                {item.subtotal !== null && !removed && (
                    <div className="flex justify-between border-t pt-1 text-xs">
                        <span className="text-muted-foreground">Subtotal</span>
                        <span className="tabular-nums font-medium">{formatRupiah(item.subtotal)}</span>
                    </div>
                )}
            </div>

            {!isTenant && item.status === 'pending' && item.proposed_price === null && (
                <p className="text-xs text-muted-foreground">Menunggu Purchasing mengajukan harga untuk item ini.</p>
            )}

            {hasActions && (
                <div className="flex flex-wrap gap-1.5">
                    {item.can.accept && (
                        <Button type="button" size="sm" className="h-8 gap-1" disabled={busy} onClick={() => onAccept(item)}>
                            <Check className="size-3.5" />
                            Setujui {formatRupiah(acceptPrice)}
                        </Button>
                    )}
                    {item.can.offer && (
                        <Button type="button" size="sm" variant="outline" className="h-8 gap-1" disabled={busy} onClick={() => onOffer(item)}>
                            <Send className="size-3.5" />
                            {offerLabel}
                        </Button>
                    )}
                    {item.can.remove && (
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="h-8 gap-1 text-destructive hover:text-destructive"
                            disabled={busy}
                            onClick={() => onRemove(item)}
                        >
                            <MinusCircle className="size-3.5" />
                            Keluarkan
                        </Button>
                    )}
                </div>
            )}
        </li>
    );
}
