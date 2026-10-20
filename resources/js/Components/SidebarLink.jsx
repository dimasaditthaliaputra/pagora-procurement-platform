import { Link, usePage } from '@inertiajs/react';
import { SidebarMenuItem, SidebarMenuButton } from '@/Components/ui/sidebar';
import { Badge } from '@/Components/ui/badge';
import { cn } from '@/lib/utils';

/**
 * Komponen SidebarLink dengan active state otomatis dan dark styling bernuansa modern.
 * Menggunakan isActive={isActive} untuk memanfaatkan data-active dari SidebarMenuButton.
 */
export function SidebarLink({
    href = '#',
    icon: Icon,
    title,
    children,
    routeName,
    active: explicitActive,
    badge,
    className,
    tooltip,
    size = 'default',
    variant = 'default',
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
            size={size}
            variant={variant}
            tooltip={tooltip || title}
            className={cn(
                "group flex h-10 w-full items-center justify-between rounded-lg px-3 transition-all duration-200",
                "text-zinc-400 hover:bg-zinc-900 hover:text-white",
                "data-[active=true]:bg-white/12 data-[active=true]:text-white data-[active=true]:font-semibold data-[active=true]:shadow-xs data-[active=true]:border data-[active=true]:border-white/10",
                className
            )}
        >
            <Link href={href} className="flex w-full items-center justify-between gap-3" {...props}>
                <div className="flex min-w-0 items-center gap-3">
                    {Icon && (
                        <Icon className="size-5 shrink-0 transition-transform duration-200 group-hover:scale-110 text-zinc-400 group-hover:text-white group-data-[active=true]:text-white" />
                    )}
                    <span className="truncate font-medium">{title || children}</span>
                </div>
                {badge && (
                    <Badge className="ml-auto h-5 px-1.5 text-[10px] font-semibold shrink-0 group-data-[collapsible=icon]:hidden bg-zinc-800 text-zinc-200 border-zinc-700">
                        {badge}
                    </Badge>
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
