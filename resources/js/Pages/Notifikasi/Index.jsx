import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    Bell,
    CheckCheck,
    FileText,
    MessageSquare,
    Handshake,
    ShieldCheck,
    Info,
    ExternalLink,
    Clock,
    Check,
} from 'lucide-react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent } from '@/Components/ui/card';
import { DataTablePagination } from '@/Components/data-table/data-table-pagination';
import { notify } from '@/lib/notify';
import { cn } from '@/lib/utils';

export default function NotificationIndex({
    notifications,
    unreadCount = 0,
    filter = 'all',
}) {
    const [markingAll, setMarkingAll] = useState(false);

    const items = notifications?.data ?? [];
    const meta = notifications?.meta ?? {
        current_page: notifications?.current_page ?? 1,
        last_page: notifications?.last_page ?? 1,
        per_page: notifications?.per_page ?? 15,
        total: notifications?.total ?? items.length,
        from: notifications?.from ?? 1,
        to: notifications?.to ?? items.length,
    };

    const handleFilterChange = (newFilter) => {
        router.get(
            '/notifikasi',
            { filter: newFilter },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handlePageChange = (page) => {
        router.get(
            '/notifikasi',
            { filter, page },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleNotificationClick = (item) => {
        router.post(`/notifications/${item.uuid}/read`, {}, {
            preserveState: !item.can_redirect,
            preserveScroll: true,
        });
    };

    const handleMarkAllAsRead = () => {
        if (unreadCount === 0 || markingAll) return;

        setMarkingAll(true);
        router.post('/notifications/read-all', {}, {
            preserveScroll: true,
            onSuccess: () => {
                notify.success('Semua notifikasi telah ditandai sebagai dibaca.');
                setMarkingAll(false);
            },
            onError: () => {
                notify.error('Gagal menandai notifikasi.');
                setMarkingAll(false);
            },
        });
    };

    const getIcon = (type) => {
        switch (type) {
            case 'negotiation':
                return <MessageSquare className="size-5 text-emerald-500" />;
            case 'purchase_order':
                return <FileText className="size-5 text-sky-500" />;
            case 'partnership':
                return <Handshake className="size-5 text-amber-500" />;
            case 'verification':
                return <ShieldCheck className="size-5 text-indigo-500" />;
            default:
                return <Info className="size-5 text-zinc-400" />;
        }
    };

    const getTypeBadge = (type) => {
        switch (type) {
            case 'negotiation':
                return (
                    <Badge variant="outline" className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20 text-xs">
                        Negosiasi
                    </Badge>
                );
            case 'purchase_order':
                return (
                    <Badge variant="outline" className="bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/20 text-xs">
                        Purchase Order
                    </Badge>
                );
            case 'partnership':
                return (
                    <Badge variant="outline" className="bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20 text-xs">
                        Kemitraan
                    </Badge>
                );
            case 'verification':
                return (
                    <Badge variant="outline" className="bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20 text-xs">
                        Verifikasi
                    </Badge>
                );
            default:
                return (
                    <Badge variant="outline" className="text-zinc-500 border-zinc-700 text-xs">
                        Sistem
                    </Badge>
                );
        }
    };

    return (
        <DashboardLayout
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Log Notifikasi' },
            ]}
        >
            <Head title="Log Notifikasi" />

            <div className="space-y-6">
                {/* Header Section */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <div className="p-2 bg-primary/10 rounded-lg text-primary">
                                <Bell className="size-5" />
                            </div>
                            <h1 className="text-2xl font-bold tracking-tight">
                                Log Notifikasi
                            </h1>
                        </div>
                        <p className="text-sm text-muted-foreground mt-1">
                            Riwayat pembaruan status PO, tawar-menawar negosiasi, dan notifikasi penting akun Anda.
                        </p>
                    </div>

                    {unreadCount > 0 && (
                        <Button
                            variant="outline"
                            onClick={handleMarkAllAsRead}
                            disabled={markingAll}
                            className="gap-2 shrink-0 border-emerald-500/30 hover:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                        >
                            <CheckCheck className="size-4" />
                            <span>Tandai Semua Terbaca ({unreadCount})</span>
                        </Button>
                    )}
                </div>

                {/* Filter Tabs */}
                <div className="flex items-center gap-2 border-b border-border pb-3">
                    <Button
                        variant={filter === 'all' ? 'default' : 'ghost'}
                        size="sm"
                        onClick={() => handleFilterChange('all')}
                        className="rounded-full text-xs"
                    >
                        Semua ({meta.total || items.length})
                    </Button>
                    <Button
                        variant={filter === 'unread' ? 'default' : 'ghost'}
                        size="sm"
                        onClick={() => handleFilterChange('unread')}
                        className="rounded-full text-xs flex items-center gap-1.5"
                    >
                        <span>Belum Dibaca</span>
                        {unreadCount > 0 && (
                            <Badge
                                variant="secondary"
                                className="bg-emerald-500 text-white text-[10px] px-1.5 py-0 h-4 min-w-4"
                            >
                                {unreadCount}
                            </Badge>
                        )}
                    </Button>
                    <Button
                        variant={filter === 'read' ? 'default' : 'ghost'}
                        size="sm"
                        onClick={() => handleFilterChange('read')}
                        className="rounded-full text-xs"
                    >
                        Sudah Dibaca
                    </Button>
                </div>

                {/* Notifications List */}
                {items.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                            <div className="size-14 rounded-full bg-muted flex items-center justify-center text-muted-foreground mb-4">
                                <Bell className="size-7" />
                            </div>
                            <h3 className="text-base font-semibold">
                                {filter === 'unread'
                                    ? 'Tidak ada notifikasi belum dibaca'
                                    : 'Belum ada riwayat notifikasi'}
                            </h3>
                            <p className="text-sm text-muted-foreground mt-1 max-w-sm">
                                {filter === 'unread'
                                    ? 'Semua notifikasi penting telah Anda baca.'
                                    : 'Aktivitas pengadaan dan pembaruan sistem akan tercatat otomatis di sini.'}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-3">
                        {items.map((item) => (
                            <div
                                key={item.uuid}
                                onClick={() => handleNotificationClick(item)}
                                className={cn(
                                    'group relative p-4 sm:p-5 rounded-xl border transition-all cursor-pointer flex flex-col sm:flex-row sm:items-center justify-between gap-4',
                                    item.is_read
                                        ? 'bg-card/50 hover:bg-card border-border/60 opacity-85 hover:opacity-100'
                                        : 'bg-emerald-500/5 hover:bg-emerald-500/10 border-emerald-500/30 shadow-xs'
                                )}
                            >
                                <div className="flex items-start gap-4 min-w-0">
                                    <div
                                        className={cn(
                                            'size-11 rounded-xl flex items-center justify-center shrink-0 border mt-0.5',
                                            item.is_read
                                                ? 'bg-muted border-border/80'
                                                : 'bg-emerald-500/15 border-emerald-500/30'
                                        )}
                                    >
                                        {getIcon(item.type)}
                                    </div>

                                    <div className="min-w-0 flex-1 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            {getTypeBadge(item.type)}
                                            {!item.is_read ? (
                                                <Badge
                                                    variant="secondary"
                                                    className="bg-emerald-500 text-white text-[10px] px-1.5 py-0 h-4 font-semibold"
                                                >
                                                    Baru
                                                </Badge>
                                            ) : (
                                                <span className="text-[11px] text-muted-foreground flex items-center gap-1">
                                                    <Check className="size-3 text-muted-foreground" />
                                                    <span>Terbaca</span>
                                                </span>
                                            )}
                                        </div>

                                        <h3
                                            className={cn(
                                                'text-sm tracking-tight',
                                                item.is_read
                                                    ? 'font-medium text-foreground/80'
                                                    : 'font-bold text-foreground'
                                            )}
                                        >
                                            {item.title}
                                        </h3>

                                        <p className="text-sm text-muted-foreground leading-relaxed">
                                            {item.message}
                                        </p>

                                        <div className="flex items-center gap-2 pt-1 text-xs text-muted-foreground">
                                            <Clock className="size-3.5" />
                                            <span>{item.created_at_human}</span>
                                        </div>
                                    </div>
                                </div>

                                {item.can_redirect && item.action_url ? (
                                    <div className="sm:self-center shrink-0 pt-2 sm:pt-0">
                                        <Button
                                            variant={item.is_read ? 'outline' : 'default'}
                                            size="sm"
                                            className="w-full sm:w-auto gap-1.5 text-xs font-medium"
                                        >
                                            <span>Buka Tautan</span>
                                            <ExternalLink className="size-3.5" />
                                        </Button>
                                    </div>
                                ) : (
                                    <div className="sm:self-center shrink-0 pt-2 sm:pt-0">
                                        <Badge
                                            variant="outline"
                                            className="text-xs bg-muted/50 border-muted-foreground/30 text-muted-foreground font-normal gap-1.5 py-1 px-2.5"
                                        >
                                            <Info className="size-3 text-muted-foreground" />
                                            <span>Hanya Pengingat</span>
                                        </Badge>
                                    </div>
                                )}
                            </div>
                        ))}

                        {/* Pagination */}
                        {meta.last_page > 1 && (
                            <DataTablePagination
                                meta={meta}
                                onPageChange={handlePageChange}
                            />
                        )}
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}

