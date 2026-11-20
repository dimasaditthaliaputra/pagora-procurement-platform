import React, { useState, useMemo } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
} from '@/Components/ui/sidebar';
import { SidebarItem } from '@/Components/SidebarLink';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { ThemeToggle } from '@/Components/ThemeToggle';
import { NotificationBell } from '@/Components/NotificationBell';
import {
    LayoutDashboard,
    ChartLine,
    Users,
    FileText,
    Settings,
    LogOut,
    UserCircle,
    Building2,
    ClipboardCheck,
    Truck,
    Boxes,
    ShieldCheck,
    Search,
    ChevronsUpDown,
    X,
    Handshake,
    Receipt,
} from 'lucide-react';
import Logo from '../../assets/logo/logo.webp';

function getInitials(name) {
    if (!name) return 'U';
    return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
}

export function AppSidebar({ className, ...props }) {
    const { props: pageProps, url } = usePage();
    const { user, auth, aplikasi } = pageProps;
    const currentPath = (url || '').split('?')[0].split('#')[0];

    const namaAplikasi = aplikasi?.nama || 'PAGORA';
    const roleLabel = auth?.role?.label ?? 'Pengguna';
    const permissions = auth?.can ?? {};
    const [searchQuery, setSearchQuery] = useState('');

    const handleLogout = () => {
        router.post('/logout');
    };

    // Navigasi dinamis berdasarkan role, abilities (Policy), dan pencarian
    const navGroups = useMemo(() => {
        const isSuperadmin = auth?.role?.name === 'superadmin';
        const isVendor = auth?.role?.name === 'vendor';
        const query = searchQuery.trim().toLowerCase();
        const isDashboardOrAnalitik =
            currentPath === '/dashboard' || currentPath.startsWith('/analitik-harga');

        const groups = [
            {
                label: 'Menu Utama',
                items: [
                    {
                        title: 'Dashboard',
                        icon: LayoutDashboard,
                        href: '/dashboard',
                        routeName: 'dashboard',
                        active: isDashboardOrAnalitik,
                    },
                    { title: 'Pengadaan', icon: FileText, href: '/purchase-order', routeName: 'purchase-order', ability: 'purchase_order.view_any' },
                    { title: 'Pengiriman', icon: Truck, href: '/pengiriman', routeName: 'pengiriman', ability: 'pengiriman.view_any' },
                    { title: 'Inspeksi QC', icon: ClipboardCheck, href: '/inspeksi-qc', routeName: 'inspeksi-qc', ability: 'penerimaan_barang.view_any' },
                    { title: 'Verifikasi Tagihan', icon: Receipt, href: '/verifikasi-tagihan', routeName: 'verifikasi-tagihan.index', ability: 'invoice.verify_any' },
                ],
            },
            {
                label: 'Master Data',
                items: [
                    { title: 'Komoditas', icon: Boxes, href: '/master/komoditas', routeName: 'master.komoditas.index', ability: 'komoditas.view_any' },
                    { title: 'Rekanan Vendor', icon: Building2, href: '/master/vendor', routeName: 'master.vendor.index', exact: true, ability: 'vendor.view_any', tenantOnly: true },
                ],
            },
            {
                label: 'Manajemen',
                items: [
                    { title: 'Mitra Industri', icon: Handshake, href: '/manajemen/mitra-industri', routeName: 'manajemen.mitra-industri.index', ability: 'tenant.view_any' },
                    { title: 'Profil Perusahaan', icon: Building2, href: '/manajemen/perusahaan', routeName: 'manajemen.perusahaan.index', ability: 'perusahaan.view' },
                    { title: 'Pengguna', icon: Users, href: '/manajemen/pengguna', routeName: 'manajemen.pengguna.index', ability: 'pengguna.view_any' },
                    { title: 'Role & Akses', icon: ShieldCheck, href: '/manajemen/role-akses', routeName: 'manajemen.role-akses.index', ability: 'role.view_any' },
                    { title: 'Pengaturan', icon: Settings, href: '/pengaturan/profil', routeName: 'pengaturan', ability: 'pengaturan.view_any' },
                ],
            },
            ...(isVendor
                ? [
                      {
                          label: 'Vendor',
                          items: [
                              { title: 'Invoice', icon: Receipt, href: '/invoice', routeName: 'invoice.index', exact: true },
                              { title: 'Calon Mitra', icon: Handshake, href: '/kerja-sama/calon-mitra', routeName: 'kerja-sama.calon-mitra.index', ability: 'kerja-sama.calon-mitra.view' },
                          ],
                      },
                  ]
                : []),
        ];

        return groups
            .map((group) => ({
                ...group,
                items: group.items.filter((item) => {
                    if (item.tenantOnly && isSuperadmin) return false;
                    if (item.ability && !permissions[item.ability]) return false;
                    if (query && !item.title.toLowerCase().includes(query)) return false;
                    return true;
                }),
            }))
            .filter((group) => group.items.length > 0);
    }, [auth?.role?.name, permissions, searchQuery, currentPath]);

    const hasResults = navGroups.length > 0;

    return (
        <Sidebar
            collapsible="icon"
            className="border-r border-zinc-800/80 bg-zinc-950 text-zinc-100 transition-all duration-200"
            {...props}
        >
            {/* Header: Logo, Theme Toggle, Notification Bell, & Quick Search */}
            <SidebarHeader className="border-b border-zinc-800/80 px-4 py-4 group-data-[collapsible=icon]:p-2 transition-all">
                <div className="flex items-center justify-between gap-2">
                    <Link
                        href="/dashboard"
                        className="group/logo flex items-center gap-2.5 min-w-0 outline-none"
                    >
                        <div className="flex aspect-square size-9 items-center justify-center rounded-xl bg-white shadow-2xs border border-white/20 p-1.5 shrink-0 transition-transform duration-200 group-hover/logo:scale-105">
                            <img
                                src={aplikasi?.logo_url || Logo}
                                alt={`Logo ${namaAplikasi}`}
                                className="size-full object-contain"
                            />
                        </div>

                        <div className="grid flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden">
                            <span className="truncate font-bold tracking-tight text-white text-base">
                                {namaAplikasi}
                            </span>

                            {aplikasi?.tagline && (
                                <span className="truncate text-[11px] text-zinc-400 font-medium">
                                    {aplikasi.tagline}
                                </span>
                            )}
                        </div>
                    </Link>

                    {/* Quick Action Icons: Theme Toggle & Notification */}
                    <div className="flex items-center gap-1 group-data-[collapsible=icon]:hidden shrink-0">
                        <ThemeToggle className="text-zinc-400 hover:text-white hover:bg-white/10" />
                        <NotificationBell />
                    </div>
                </div>

                {/* Search Bar */}
                <div className="relative mt-3 w-full group-data-[collapsible=icon]:hidden">
                    <Search className="absolute top-2.5 left-3 size-4 text-zinc-500 pointer-events-none" />

                    <Input
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder="Cari menu..."
                        className="h-9 bg-zinc-900/90 pl-9 pr-8 text-xs shadow-none hover:bg-zinc-900 focus:bg-zinc-900 rounded-lg border-zinc-800 text-zinc-100 transition-colors placeholder:text-zinc-500 focus:border-zinc-700"
                    />

                    {searchQuery && (
                        <button
                            type="button"
                            onClick={() => setSearchQuery('')}
                            className="absolute top-2.5 right-2.5 text-zinc-400 hover:text-white transition-colors"
                        >
                            <X className="size-4" />
                            <span className="sr-only">Hapus pencarian</span>
                        </button>
                    )}
                </div>
            </SidebarHeader>

            {/* Navigation Content */}
            <SidebarContent className="px-3 py-3 gap-2">
                {!hasResults ? (
                    <div className="flex flex-col items-center justify-center py-8 px-3 text-center">
                        <Search className="size-6 text-zinc-600 mb-2" />
                        <p className="text-xs font-medium text-zinc-400">
                            Tidak ada menu ditemukan
                        </p>
                        <p className="text-[11px] text-zinc-500 mt-0.5">
                            Coba kata kunci pencarian lain
                        </p>
                    </div>
                ) : (
                    navGroups.map((group, index) => (
                        <SidebarGroup key={group.label} className={index > 0 ? 'mt-2' : ''}>
                            <SidebarGroupLabel className="px-2 text-[11px] font-semibold tracking-wider text-zinc-500 uppercase">
                                {group.label}
                            </SidebarGroupLabel>

                            <SidebarGroupContent className="mt-1">
                                <SidebarMenu className="gap-1">
                                    {group.items.map((item) => (
                                        <SidebarItem
                                            key={item.title}
                                            title={item.title}
                                            icon={item.icon}
                                            href={item.href}
                                            routeName={item.routeName}
                                            exact={item.exact}
                                            active={item.active}
                                            badge={item.badge}
                                        />
                                    ))}
                                </SidebarMenu>
                            </SidebarGroupContent>
                        </SidebarGroup>
                    ))
                )}
            </SidebarContent>

            {/* Footer: User Profile Card & Account Dropdown */}
            <SidebarFooter className="mt-auto border-t border-zinc-800/80 p-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <SidebarMenuButton
                                    size="lg"
                                    className="group flex h-12 w-full items-center gap-3 rounded-lg p-2 transition-all hover:bg-zinc-900 data-[state=open]:bg-zinc-900"
                                >
                                    <Avatar className="size-9 rounded-lg border border-zinc-800 shrink-0">
                                        <AvatarImage
                                            src={user?.avatar_url}
                                            alt={user?.name}
                                        />
                                        <AvatarFallback className="rounded-lg bg-zinc-800 font-bold text-white text-xs border border-zinc-700">
                                            {getInitials(user?.name)}
                                        </AvatarFallback>
                                    </Avatar>

                                    <div className="grid flex-1 text-left text-sm leading-tight overflow-hidden group-data-[collapsible=icon]:hidden">
                                        <span className="truncate font-semibold text-white text-xs">
                                            {user?.name ?? 'Pengguna'}
                                        </span>
                                        <span className="truncate text-[11px] text-zinc-400">
                                            {user?.email ?? roleLabel}
                                        </span>
                                    </div>

                                    <ChevronsUpDown className="size-4 text-zinc-500 group-data-[collapsible=icon]:hidden shrink-0 ml-auto" />
                                </SidebarMenuButton>
                            </DropdownMenuTrigger>

                            <DropdownMenuContent
                                className="dark w-56 rounded-xl border border-zinc-800 bg-zinc-950 text-zinc-100 p-1.5 shadow-xl"
                                side="top"
                                align="end"
                                sideOffset={8}
                            >
                                <DropdownMenuLabel className="p-0 font-normal">
                                    <div className="flex items-center gap-2.5 px-2 py-2 text-left text-sm">
                                        <Avatar className="size-8 rounded-lg border border-zinc-800">
                                            <AvatarImage
                                                src={user?.avatar_url}
                                                alt={user?.name}
                                            />
                                            <AvatarFallback className="rounded-lg bg-zinc-800 font-bold text-white text-xs">
                                                {getInitials(user?.name)}
                                            </AvatarFallback>
                                        </Avatar>

                                        <div className="grid flex-1 text-left text-xs leading-tight">
                                            <span className="truncate font-semibold text-white">
                                                {user?.name}
                                            </span>
                                            <span className="truncate text-[10px] text-zinc-400">
                                                {roleLabel}
                                            </span>
                                        </div>
                                    </div>
                                </DropdownMenuLabel>

                                <DropdownMenuSeparator className="my-1 bg-zinc-800" />

                                <DropdownMenuItem
                                    asChild
                                    className="rounded-lg cursor-pointer text-xs py-2 gap-2 text-zinc-300 hover:text-white hover:bg-zinc-900 focus:bg-zinc-900 focus:text-white"
                                >
                                    <Link href="/profil" className="flex items-center gap-2 w-full">
                                        <UserCircle className="size-4 shrink-0 text-zinc-400 group-hover/dropdown-menu-item:text-white group-focus/dropdown-menu-item:text-white transition-colors" />
                                        <span>Profil Saya</span>
                                    </Link>
                                </DropdownMenuItem>

                                {permissions?.['perusahaan.view'] && (
                                    <DropdownMenuItem
                                        asChild
                                        className="rounded-lg cursor-pointer text-xs py-2 gap-2 text-zinc-300 hover:text-white hover:bg-zinc-900 focus:bg-zinc-900 focus:text-white"
                                    >
                                        <Link href="/manajemen/perusahaan" className="flex items-center gap-2 w-full">
                                            <Building2 className="size-4 shrink-0 text-zinc-400 group-hover/dropdown-menu-item:text-white group-focus/dropdown-menu-item:text-white transition-colors" />
                                            <span>Profil Perusahaan</span>
                                        </Link>
                                    </DropdownMenuItem>
                                )}

                                <DropdownMenuSeparator className="my-1 bg-zinc-800" />

                                <DropdownMenuItem
                                    variant="destructive"
                                    onClick={handleLogout}
                                    className="rounded-lg cursor-pointer text-xs py-2 gap-2 text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 focus:bg-rose-500/10 focus:text-rose-300"
                                >
                                    <LogOut className="size-4 shrink-0 text-rose-400 group-hover/dropdown-menu-item:text-rose-300 group-focus/dropdown-menu-item:text-rose-300 transition-colors" />
                                    <span>Keluar</span>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>

            <SidebarRail />
        </Sidebar>
    );
}
