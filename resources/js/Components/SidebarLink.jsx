import { Link, usePage } from '@inertiajs/react';
import { SidebarMenuItem, SidebarMenuButton } from '@/Components/ui/sidebar';

/**
 * Komponen SidebarLink dengan active state otomatis dan styling bawaan shadcn/ui.
 * Menggunakan isActive={isActive} untuk memanfaatkan data-active dari SidebarMenuButton.
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
            className={className}
        >
            <Link href={href} {...props}>
                {Icon && <Icon />}
                <span>{title || children}</span>
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

