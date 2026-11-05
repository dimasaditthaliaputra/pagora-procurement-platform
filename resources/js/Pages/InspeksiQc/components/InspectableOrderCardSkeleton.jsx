import { Skeleton } from '@/Components/ui/skeleton';
import {
    INSPECTABLE_TABULAR_GRID,
    InspectableOrderTableHeader,
} from '@/Pages/InspeksiQc/components/InspectableOrderCard';

export function InspectableOrderCardSkeleton() {
    return (
        <div
            aria-hidden="true"
            className={`flex flex-col gap-3 rounded-xl border border-border/60 bg-card px-4 py-3.5 shadow-2xs ${INSPECTABLE_TABULAR_GRID}`}
        >
            {/* Kolom 1: PO & Vendor */}
            <div className="space-y-1.5">
                <div className="flex items-center gap-2">
                    <Skeleton className="h-4 w-28" />
                    <Skeleton className="h-4 w-14 rounded-full" />
                </div>
                <Skeleton className="h-3.5 w-40" />
                <Skeleton className="h-3 w-24" />
            </div>

            {/* Kolom 2: Armada & Jalur */}
            <div className="space-y-1.5">
                <div className="flex items-center gap-2">
                    <Skeleton className="h-4 w-32" />
                    <Skeleton className="h-4 w-16 rounded" />
                </div>
                <Skeleton className="h-3.5 w-28" />
            </div>

            {/* Kolom 3: Item & ETA */}
            <div className="space-y-1.5">
                <Skeleton className="h-4 w-24" />
                <Skeleton className="h-3.5 w-28" />
            </div>

            {/* Kolom 4: Tombol Aksi */}
            <div className="flex items-center justify-end border-t border-border/60 pt-3 lg:border-t-0 lg:pt-0">
                <Skeleton className="h-9 w-full lg:w-44 rounded-lg" />
            </div>
        </div>
    );
}

export function InspectableOrderGridSkeleton({ count = 4 }) {
    return (
        <div
            role="status"
            aria-label="Memuat daftar pesanan dalam pengiriman..."
            className="space-y-2.5"
        >
            <InspectableOrderTableHeader />
            <div className="space-y-2.5">
                {Array.from({ length: count }).map((_, index) => (
                    <InspectableOrderCardSkeleton key={`inspectable-skeleton-${index}`} />
                ))}
            </div>
        </div>
    );
}

export default InspectableOrderCardSkeleton;
