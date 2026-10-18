import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { SidebarMenuItem, SidebarMenuButton } from '@/Components/ui/sidebar';

/**
 * Komponen SidebarLink dengan active state otomatis dan styling kontras yang nyaman di mata.
 *
 * Perbaikan bug:
 * 1. Mengisolasi hover state ke masing-masing item (menghilangkan group-hover global yang menyebabkan semua icon aktif saat 1 menu di-hover).
 * 2. Icon aktif tetap menggunakan warna emerald-600 yang tajam dan tidak hilang.
 * 3. Icon non-aktif mewarisi currentColor dari link, sehingga hanya icon menu yang di-hover yang berubah warna.
 */
export function SidebarLink({
    href = '#',
    icon: Icon,
    title,
    children,
    routeName,
    active: explicitActive,
    className,
    tooltip,
    ...props
}) {
    const { url } = usePage();

    // Deteksi active state via Ziggy route().current() atau usePage().url dari Inertia
    const isActive = explicitActive !== undefined
        ? Boolean(explicitActive)
        : (() => {
            // Cek jika Ziggy route() tersedia dan routeName diberikan
            if (typeof route === 'function' && routeName) {
                try {
                    if (route().current(routeName) || route().current(`${routeName}.*`)) {
                        return true;
                    }
                } catch {
                    // Fallback ke pengecekan URL jika route() gagal
                }
            }

            // Jika link placeholder '#' atau kosong, tidak aktif
            if (!href || href === '#') {
                return false;
            }

            // Normalisasi URL saat ini dan target href (buang query params dan hash)
            const currentPath = (url || '').split('?')[0].split('#')[0];
            const targetPath = href.split('?')[0].split('#')[0];

            if (targetPath === '/' || targetPath === '/dashboard') {
                return currentPath === targetPath;
            }

            return currentPath === targetPath || (targetPath !== '' && currentPath.startsWith(`${targetPath}/`));
        })();

    return (
        <SidebarMenuButton
            asChild
            isActive={isActive}
            tooltip={tooltip || title}
            className={cn(
                'relative w-full rounded-md p-2 transition-colors duration-100',
                isActive
                    ? [
                        '!bg-slate-100 dark:!bg-zinc-800/90',
                        'border border-slate-200/90 dark:border-zinc-700/80',
                        '!text-slate-900 dark:!text-zinc-50 font-semibold shadow-2xs',
                        'pl-3.5',
                        'hover:!bg-slate-100 dark:hover:!bg-zinc-800',
                        'hover:!text-slate-900 dark:hover:!text-zinc-50',
                    ]
                    : [
                        'bg-transparent font-normal text-muted-foreground',
                        'hover:bg-muted/70 hover:text-foreground',
                    ],
                className
            )}
        >
            <Link href={href} {...props}>
                {/* Active Indicator Bar di sisi kiri */}
                {isActive && (
                    <span
                        className="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-emerald-600 dark:bg-emerald-400 group-data-[collapsible=icon]:hidden"
                        aria-hidden="true"
                    />
                )}

                {/* Icon menu: aktif berwarna hitam senada dengan teks (hitam bold), non-aktif mengikuti currentColor hover */}
                {Icon && (
                    <Icon
                        strokeWidth={isActive ? 2.25 : 2}
                        className={cn(
                            'size-4 shrink-0 transition-colors duration-100',
                            isActive
                                ? '!text-slate-900 dark:!text-zinc-50'
                                : 'text-current'
                        )}
                    />
                )}

                {/* Label teks menu */}
                <span className="truncate flex-1 text-left">{title || children}</span>

                {/* Dot aksen di sisi kanan */}
                {isActive && (
                    <span
                        className="size-1.5 rounded-full shrink-0 bg-emerald-500 shadow-[0_0_6px_rgba(16,185,129,0.5)] group-data-[collapsible=icon]:hidden"
                        aria-hidden="true"
                    />
                )}
            </Link>
        </SidebarMenuButton>
    );
}

/**
 * Komponen pembungkus SidebarItem (SidebarMenuItem + SidebarLink)
 */
export function SidebarItem(props) {
    return (
        <SidebarMenuItem>
            <SidebarLink {...props} />
        </SidebarMenuItem>
    );
}

export default SidebarLink;
