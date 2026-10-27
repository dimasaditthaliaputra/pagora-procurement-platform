import { Lock } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { STATUS_PO, isLocked } from '@/Pages/PurchaseOrder/constants';

export function StatusBadge({ po, showLock = false }) {
    const status = STATUS_PO[po?.status] ?? { label: po?.status ?? '-', variant: 'secondary' };
    const locked = showLock && isLocked(po) && po?.status !== 'dibatalkan';

    return (
        <Badge variant={status.variant} className="gap-1 whitespace-nowrap">
            {locked && <Lock className="size-3" />}
            {status.label}
        </Badge>
    );
}
