import { Link } from '@inertiajs/react';
import { ClipboardCheck, ShieldAlert } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Badge } from '@/Components/ui/badge';

export function InspeksiQcNavTabs({ activeTab = 'inspections', damageCount = null }) {
    const tabs = [
        {
            id: 'inspections',
            label: 'Pengecekan & Riwayat Inspeksi',
            href: '/inspeksi-qc',
            icon: ClipboardCheck,
            badge: null,
        },
        {
            id: 'damage_reports',
            label: 'Laporan Kerusakan Barang',
            href: '/inspeksi-qc/laporan-kerusakan',
            icon: ShieldAlert,
            badge: typeof damageCount === 'number' && damageCount > 0 ? damageCount : null,
        },
    ];

    return (
        <nav
            aria-label="Navigasi modul Inspeksi QC"
            className="inline-flex flex-wrap sm:flex-nowrap items-center gap-1 rounded-xl border bg-muted/40 p-1 w-full sm:w-fit shrink-0"
        >
            {tabs.map((tab) => {
                const Icon = tab.icon;
                const isActive = activeTab === tab.id;

                return (
                    <Link
                        key={tab.id}
                        href={tab.href}
                        className={cn(
                            'inline-flex flex-1 sm:flex-initial items-center justify-center gap-2 rounded-lg px-3.5 h-8 text-xs sm:text-sm font-semibold whitespace-nowrap transition-colors',
                            isActive
                                ? 'bg-background text-foreground shadow-2xs border border-border/60'
                                : 'text-muted-foreground hover:text-foreground hover:bg-background/50'
                        )}
                    >
                        <Icon
                            className={cn(
                                'size-4 shrink-0',
                                isActive
                                    ? tab.id === 'damage_reports'
                                        ? 'text-destructive'
                                        : 'text-primary'
                                    : 'text-muted-foreground'
                            )}
                        />
                        <span>{tab.label}</span>
                        {tab.badge !== null && (
                            <Badge
                                variant={tab.id === 'damage_reports' ? 'destructive' : 'secondary'}
                                className="px-1.5 py-0 text-[10px] leading-4"
                            >
                                {tab.badge}
                            </Badge>
                        )}
                    </Link>
                );
            })}
        </nav>
    );
}

export default InspeksiQcNavTabs;

