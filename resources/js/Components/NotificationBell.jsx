import { useState, useEffect, useCallback } from 'react';
import { usePage, router, Link } from '@inertiajs/react';
import {
    Bell,
    CheckCheck,
    FileText,
    MessageSquare,
    Handshake,
    ShieldCheck,
    Info,
    ExternalLink,
    Loader2,
    Check,
} from 'lucide-react';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { notify } from '@/lib/notify';
import { cn } from '@/lib/utils';

export function NotificationBell({ className }) {
    const { unread_notifications_count: sharedUnreadCount } = usePage().props;
    const [isOpen, setIsOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [markingAll, setMarkingAll] = useState(false);
    const [notifications, setNotifications] = useState([]);
    const [unreadCount, setUnreadCount] = useState(sharedUnreadCount || 0);
    const [filter, setFilter] = useState('all'); // 'all' | 'unread'

    // Update unread count when shared prop changes
    useEffect(() => {
        if (sharedUnreadCount !== undefined) {
            setUnreadCount(sharedUnreadCount);
        }
    }, [sharedUnreadCount]);

    // Fetch recent notifications when popover is opened
    const fetchNotifications = useCallback(async () => {
        setLoading(true);
        try {
            const response = await fetch('/notifications?limit=10', {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (response.ok) {
                const data = await response.json();
                setNotifications(data.notifications || []);
                if (typeof data.unread_count === 'number') {
                    setUnreadCount(data.unread_count);
                }
            }
        } catch (error) {
            console.error('Failed to load notifications:', error);
        } finally {
            setLoading(false);
        }
    }, []);

    const handleOpenChange = (open) => {
        setIsOpen(open);
        if (open) {
            fetchNotifications();
        }
    };

    const handleNotificationClick = async (item) => {
        // Optimistically mark as read in local state
        if (!item.is_read) {
            setNotifications((prev) =>
                prev.map((n) => (n.uuid === item.uuid ? { ...n, is_read: true } : n))
            );
            setUnreadCount((prev) => Math.max(0, prev - 1));

            // Send read request
            try {
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content');

                fetch(`/notifications/${item.uuid}/read`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    },
                });
            } catch (err) {
                console.error('Failed to mark notification read:', err);
            }
        }

        setIsOpen(false);

        // Redirect to action URL only if permitted for user's role
        if (item.can_redirect && item.action_url) {
            router.visit(item.action_url);
        }
    };

    const handleMarkAllAsRead = async (e) => {
        e.stopPropagation();
        if (unreadCount === 0 || markingAll) return;

        setMarkingAll(true);
        // Optimistically mark all read
        setNotifications((prev) => prev.map((n) => ({ ...n, is_read: true })));
        setUnreadCount(0);

        try {
            const csrfToken = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content');

            const res = await fetch('/notifications/read-all', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
            });

            if (res.ok) {
                notify.success('Semua notifikasi ditandai telah dibaca.');
            }
        } catch (err) {
            console.error('Failed to mark all as read:', err);
        } finally {
            setMarkingAll(false);
        }
    };

    const filteredList = notifications.filter((n) => {
        if (filter === 'unread') return !n.is_read;
        return true;
    });

    const getIcon = (type) => {
        switch (type) {
            case 'negotiation':
                return <MessageSquare className="size-4 text-emerald-400" />;
            case 'purchase_order':
                return <FileText className="size-4 text-sky-400" />;
            case 'partnership':
                return <Handshake className="size-4 text-amber-400" />;
            case 'verification':
                return <ShieldCheck className="size-4 text-indigo-400" />;
            default:
                return <Info className="size-4 text-zinc-400" />;
        }
    };

    return (
        <Popover open={isOpen} onOpenChange={handleOpenChange}>
            <PopoverTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className={cn(
                        'size-8 rounded-full text-zinc-400 hover:bg-white/10 hover:text-white relative shrink-0 transition-colors',
                        isOpen && 'bg-white/10 text-white',
                        className
                    )}
                    title="Notifikasi"
                    type="button"
                >
                    <Bell className="size-4" />
                    {unreadCount > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-emerald-500 px-1 text-[10px] font-bold text-white shadow-xs animate-in zoom-in-50">
                            {unreadCount > 9 ? '9+' : unreadCount}
                        </span>
                    )}
                    <span className="sr-only">Notifikasi</span>
                </Button>
            </PopoverTrigger>

            <PopoverContent
                align="start"
                sideOffset={8}
                className="w-80 sm:w-96 p-0 rounded-xl bg-zinc-900 border-zinc-800 text-zinc-100 shadow-xl overflow-hidden z-50"
            >
                {/* Header */}
                <div className="flex items-center justify-between px-4 py-3 border-b border-zinc-800/80 bg-zinc-950/60">
                    <div className="flex items-center gap-2">
                        <span className="font-semibold text-sm tracking-tight text-white">
                            Notifikasi
                        </span>
                        {unreadCount > 0 ? (
                            <Badge
                                variant="secondary"
                                className="bg-emerald-500/15 text-emerald-400 border border-emerald-500/20 text-[11px] px-1.5 py-0 h-5"
                            >
                                {unreadCount} baru
                            </Badge>
                        ) : (
                            <Badge
                                variant="outline"
                                className="border-zinc-700 text-zinc-400 text-[11px] px-1.5 py-0 h-5"
                            >
                                Semua terbaca
                            </Badge>
                        )}
                    </div>

                    {unreadCount > 0 && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={handleMarkAllAsRead}
                            disabled={markingAll}
                            className="h-7 text-xs text-zinc-400 hover:text-emerald-400 hover:bg-zinc-800/60 px-2 gap-1"
                            title="Tandai semua telah dibaca"
                        >
                            {markingAll ? (
                                <Loader2 className="size-3.5 animate-spin" />
                            ) : (
                                <CheckCheck className="size-3.5" />
                            )}
                            <span>Tandai dibaca</span>
                        </Button>
                    )}
                </div>

                {/* Filter Tabs */}
                <div className="flex items-center gap-1 px-3 py-1.5 bg-zinc-950/30 border-b border-zinc-800/50 text-xs">
                    <button
                        type="button"
                        onClick={() => setFilter('all')}
                        className={cn(
                            'px-2.5 py-1 rounded-md transition-colors font-medium',
                            filter === 'all'
                                ? 'bg-zinc-800 text-white'
                                : 'text-zinc-400 hover:text-zinc-200'
                        )}
                    >
                        Semua ({notifications.length})
                    </button>
                    <button
                        type="button"
                        onClick={() => setFilter('unread')}
                        className={cn(
                            'px-2.5 py-1 rounded-md transition-colors font-medium flex items-center gap-1.5',
                            filter === 'unread'
                                ? 'bg-zinc-800 text-white'
                                : 'text-zinc-400 hover:text-zinc-200'
                        )}
                    >
                        <span>Belum Dibaca</span>
                        {unreadCount > 0 && (
                            <span className="size-1.5 rounded-full bg-emerald-500" />
                        )}
                    </button>
                </div>

                {/* Content List */}
                <div className="max-h-[360px] overflow-y-auto divide-y divide-zinc-800/50">
                    {loading ? (
                        <div className="flex flex-col items-center justify-center py-10 text-zinc-500 gap-2">
                            <Loader2 className="size-6 animate-spin text-zinc-400" />
                            <span className="text-xs">Memuat notifikasi...</span>
                        </div>
                    ) : filteredList.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-10 px-4 text-center text-zinc-400">
                            <div className="size-10 rounded-full bg-zinc-800/60 flex items-center justify-center mb-2 text-zinc-500">
                                <Bell className="size-5" />
                            </div>
                            <p className="text-sm font-medium text-zinc-300">
                                {filter === 'unread'
                                    ? 'Tidak ada notifikasi belum dibaca'
                                    : 'Belum ada notifikasi'}
                            </p>
                            <p className="text-xs text-zinc-500 mt-1 max-w-[240px]">
                                {filter === 'unread'
                                    ? 'Semua notifikasi penting telah Anda baca.'
                                    : 'Aktivitas pengadaan dan pembaruan sistem akan muncul di sini.'}
                            </p>
                        </div>
                    ) : (
                        filteredList.map((item) => (
                            <div
                                key={item.uuid}
                                onClick={() => handleNotificationClick(item)}
                                className={cn(
                                    'group flex items-start gap-3 p-3.5 text-left cursor-pointer transition-colors relative',
                                    item.is_read
                                        ? 'hover:bg-zinc-800/40 opacity-80 hover:opacity-100'
                                        : 'bg-emerald-500/10 hover:bg-emerald-500/15 border-l-2 border-emerald-500'
                                )}
                            >
                                <div
                                    className={cn(
                                        'size-8 rounded-lg flex items-center justify-center shrink-0 border mt-0.5',
                                        item.is_read
                                            ? 'bg-zinc-800/60 border-zinc-700/60 text-zinc-400'
                                            : 'bg-emerald-500/15 border-emerald-500/30'
                                    )}
                                >
                                    {getIcon(item.type)}
                                </div>

                                <div className="flex-1 min-w-0 pr-2">
                                    <div className="flex items-center justify-between gap-2">
                                        <h4
                                            className={cn(
                                                'text-xs truncate tracking-tight',
                                                item.is_read
                                                    ? 'text-zinc-300 font-medium'
                                                    : 'text-white font-semibold'
                                            )}
                                        >
                                            {item.title}
                                        </h4>
                                        <span className="text-[10px] text-zinc-500 shrink-0">
                                            {item.created_at_human}
                                        </span>
                                    </div>

                                    <p className="text-xs text-zinc-400 line-clamp-2 mt-0.5 leading-relaxed">
                                        {item.message}
                                    </p>

                                    {item.can_redirect && item.action_url ? (
                                        <span className="inline-flex items-center gap-1 text-[11px] text-emerald-400 font-medium mt-1.5 group-hover:underline">
                                            <span>Lihat detail</span>
                                            <ExternalLink className="size-3" />
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1 text-[10px] text-zinc-500 font-medium mt-1.5">
                                            <span>Pengingat info</span>
                                        </span>
                                    )}
                                </div>

                                {!item.is_read && (
                                    <span
                                        className="size-2 rounded-full bg-emerald-500 shrink-0 mt-2 shadow-xs"
                                        title="Belum dibaca"
                                    />
                                )}
                            </div>
                        ))
                    )}
                </div>

                {/* Footer */}
                <div className="p-2 border-t border-zinc-800/80 bg-zinc-950/80 text-center">
                    <Link
                        href="/notifikasi"
                        onClick={() => setIsOpen(false)}
                        className="inline-flex items-center justify-center w-full py-1.5 px-3 text-xs font-medium text-zinc-300 hover:text-white hover:bg-zinc-800/60 rounded-md transition-colors"
                    >
                        Lihat Seluruh Log Notifikasi
                    </Link>
                </div>
            </PopoverContent>
        </Popover>
    );
}

