import React, { useState, useMemo } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { useTheme } from 'next-themes';
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
    DropdownMenuSub,
    DropdownMenuSubTrigger,
    DropdownMenuSubContent,
} from '@/Components/ui/dropdown-menu';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { ThemeToggle } from '@/Components/ThemeToggle';
import {
    LayoutDashboard,
    ShoppingCart,
    Package,
    Users,
    FileText,
    Settings,
    LogOut,
    UserCircle,
    Building2,
    ClipboardCheck,
    BarChart3,
    Bell,
    Truck,
    Boxes,
    TrendingUp,
    ShieldCheck,
    Search,
    ChevronsUpDown,
    X,
    Sun,
    Moon,
    Monitor,
    Check,
} from 'lucide-react';
import Logo from '../../assets/logo/logo.webp';

const mainNavItems = [
    { title: 'Dashboard', icon: LayoutDashboard, href: '/dashboard', routeName: 'dashboard' },
    { title: 'Pengadaan', icon: ShoppingCart, href: '#', badge: 'Baru' },
    { title: 'Katalog Produk', icon: Package, href: '#' },
    { title: 'Vendor', icon: Building2, href: '#' },
    { title: 'Purchase Order', icon: FileText, href: '#' },
    { title: 'Pengiriman', icon: Truck, href: '#' },
];

const masterNavItems = [
    { title: 'Komoditas', icon: Boxes, href: '/master/komoditas', routeName: 'master.komoditas.index', badge: 'Utama' },
    { title: 'Harga Referensi & Pagu', icon: TrendingUp, href: '#' },
    { title: 'Data Vendor', icon: Building2, href: '/master/vendor/verifikasi-dokumen', routeName: 'master.vendor.verifikasi-dokumen.index' },
    { title: 'Role & Akses', icon: ShieldCheck, href: '#' },
];

const managementNavItems = [
    { title: 'Inspeksi QC', icon: ClipboardCheck, href: '#' },
    { title: 'Laporan', icon: BarChart3, href: '#' },
    { title: 'Notifikasi', icon: Bell, href: '#' },
    { title: 'Pengguna', icon: Users, href: '/manajemen/pengguna', routeName: 'manajemen.pengguna.index' },
    { title: 'Pengaturan', icon: Settings, href: '#' },
];

function getRoleName(roleId) {
    switch (roleId) {
        case 1: return 'Superadmin';
        case 2: return 'Buyer';
        case 3: return 'QC Gudang';
        case 4: return 'Vendor';
        default: return 'User';
    }
}

function getInitials(name) {
    if (!name) return 'U';
    return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
}

export function AppSidebar({ className, ...props }) {
    const { user } = usePage().props;
    const { theme, setTheme } = useTheme();
    const [searchQuery, setSearchQuery] = useState('');

    const handleLogout = () => {
        router.post('/logout');
    };

    // Filter menu berdasarkan query pencarian
    const filterItems = (items) => {
        if (!searchQuery.trim()) return items;
        return items.filter((item) =>
            item.title.toLowerCase().includes(searchQuery.toLowerCase().trim())
        );
    };

    const filteredMain = useMemo(() => filterItems(mainNavItems), [searchQuery]);
    const filteredMaster = useMemo(() => filterItems(masterNavItems), [searchQuery]);
    const filteredManagement = useMemo(() => filterItems(managementNavItems), [searchQuery]);

    const hasResults = filteredMain.length > 0 || filteredMaster.length > 0 || filteredManagement.length > 0;

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
                            <img src={Logo} alt="Logo PAGORA" className="size-full object-contain" />
                        </div>
                        <div className="grid flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden">
                            <span className="truncate font-bold tracking-tight text-white text-base">
                                PAGORA
                            </span>
                            <span className="truncate text-[11px] text-zinc-400 font-medium">
                                E-Procurement
                            </span>
                        </div>
                    </Link>

                    {/* Quick Action Icons: Theme Toggle & Notification */}
                    <div className="flex items-center gap-1 group-data-[collapsible=icon]:hidden shrink-0">
                        <ThemeToggle className="text-zinc-400 hover:text-white hover:bg-white/10" />

                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8 rounded-full text-zinc-400 hover:bg-white/10 hover:text-white relative shrink-0"
                            title="Notifikasi"
                            type="button"
                        >
                            <Bell className="size-4" />
                            <span className="absolute top-1.5 right-1.5 size-2 rounded-full bg-emerald-500 animate-pulse" />
                            <span className="sr-only">Notifikasi</span>
                        </Button>
                    </div>
                </div>

                {/* Search Bar (Auto-hides on collapsed mode) */}
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
                        <p className="text-xs font-medium text-zinc-400">Tidak ada menu ditemukan</p>
                        <p className="text-[11px] text-zinc-500 mt-0.5">Coba kata kunci pencarian lain</p>
                    </div>
                ) : (
                    <>
                        {filteredMain.length > 0 && (
                            <SidebarGroup>
                                <SidebarGroupLabel className="px-2 text-[11px] font-semibold tracking-wider text-zinc-500 uppercase">
                                    Menu Utama
                                </SidebarGroupLabel>
                                <SidebarGroupContent className="mt-1">
                                    <SidebarMenu className="gap-1">
                                        {filteredMain.map((item) => (
                                            <SidebarItem
                                                key={item.title}
                                                title={item.title}
                                                icon={item.icon}
                                                href={item.href}
                                                routeName={item.routeName}
                                                badge={item.badge}
                                            />
                                        ))}
                                    </SidebarMenu>
                                </SidebarGroupContent>
                            </SidebarGroup>
                        )}

                        {filteredMaster.length > 0 && (
                            <SidebarGroup className="mt-2">
                                <SidebarGroupLabel className="px-2 text-[11px] font-semibold tracking-wider text-zinc-500 uppercase">
                                    Master Data
                                </SidebarGroupLabel>
                                <SidebarGroupContent className="mt-1">
                                    <SidebarMenu className="gap-1">
                                        {filteredMaster.map((item) => (
                                            <SidebarItem
                                                key={item.title}
                                                title={item.title}
                                                icon={item.icon}
                                                href={item.href}
                                                routeName={item.routeName}
                                                badge={item.badge}
                                            />
                                        ))}
                                    </SidebarMenu>
                                </SidebarGroupContent>
                            </SidebarGroup>
                        )}

                        {filteredManagement.length > 0 && (
                            <SidebarGroup className="mt-2">
                                <SidebarGroupLabel className="px-2 text-[11px] font-semibold tracking-wider text-zinc-500 uppercase">
                                    Manajemen
                                </SidebarGroupLabel>
                                <SidebarGroupContent className="mt-1">
                                    <SidebarMenu className="gap-1">
                                        {filteredManagement.map((item) => (
                                            <SidebarItem
                                                key={item.title}
                                                title={item.title}
                                                icon={item.icon}
                                                href={item.href}
                                                routeName={item.routeName}
                                                badge={item.badge}
                                            />
                                        ))}
                                    </SidebarMenu>
                                </SidebarGroupContent>
                            </SidebarGroup>
                        )}
                    </>
                )}
            </SidebarContent>

            {/* Footer: User Profile Card & Dropdown with Theme Changer */}
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
                                        <AvatarImage src={user?.avatar} alt={user?.nama_lengkap} />
                                        <AvatarFallback className="rounded-lg bg-zinc-800 font-bold text-white text-xs border border-zinc-700">
                                            {getInitials(user?.nama_lengkap)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="grid flex-1 text-left text-sm leading-tight overflow-hidden group-data-[collapsible=icon]:hidden">
                                        <span className="truncate font-semibold text-white text-xs">
                                            {user?.nama_lengkap ?? 'Pengguna'}
                                        </span>
                                        <span className="truncate text-[11px] text-zinc-400">
                                            {user?.email ?? getRoleName(user?.role_id)}
                                        </span>
                                    </div>
                                    <ChevronsUpDown className="size-4 text-zinc-500 group-data-[collapsible=icon]:hidden shrink-0 ml-auto" />
                                </SidebarMenuButton>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                className="w-56 rounded-xl border border-zinc-800 bg-zinc-950 text-zinc-100 p-1.5 shadow-xl"
                                side="top"
                                align="end"
                                sideOffset={8}
                            >
                                <DropdownMenuLabel className="p-0 font-normal">
                                    <div className="flex items-center gap-2.5 px-2 py-2 text-left text-sm">
                                        <Avatar className="size-8 rounded-lg border border-zinc-800">
                                            <AvatarImage src={user?.avatar} alt={user?.nama_lengkap} />
                                            <AvatarFallback className="rounded-lg bg-zinc-800 font-bold text-white text-xs">
                                                {getInitials(user?.nama_lengkap)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div className="grid flex-1 text-left text-xs leading-tight">
                                            <span className="truncate font-semibold text-white">
                                                {user?.nama_lengkap}
                                            </span>
                                            <span className="truncate text-[10px] text-zinc-400">
                                                {getRoleName(user?.role_id)}
                                            </span>
                                        </div>
                                    </div>
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator className="my-1 bg-zinc-800" />
                                <DropdownMenuItem className="rounded-lg cursor-pointer text-xs py-2 gap-2 text-zinc-300 hover:text-white hover:bg-zinc-900 focus:bg-zinc-900 focus:text-white">
                                    <UserCircle className="size-4 text-zinc-400" />
                                    Profil Saya
                                </DropdownMenuItem>
                                <DropdownMenuItem className="rounded-lg cursor-pointer text-xs py-2 gap-2 text-zinc-300 hover:text-white hover:bg-zinc-900 focus:bg-zinc-900 focus:text-white">
                                    <Settings className="size-4 text-zinc-400" />
                                    Pengaturan
                                </DropdownMenuItem>

                                {/* Theme Switcher Submenu */}
                                <DropdownMenuSub>
                                    <DropdownMenuSubTrigger className="rounded-lg cursor-pointer text-xs py-2 gap-2 text-zinc-300 hover:text-white hover:bg-zinc-900 focus:bg-zinc-900 focus:text-white">
                                        <Sun className="size-4 text-amber-500 rotate-0 scale-100 dark:-rotate-90 dark:scale-0 transition-all" />
                                        <Moon className="absolute size-4 text-sky-400 rotate-90 scale-0 dark:rotate-0 dark:scale-100 transition-all" />
                                        <span className="ml-0.5">Tema Tampilan</span>
                                    </DropdownMenuSubTrigger>
                                    <DropdownMenuSubContent className="rounded-xl border border-zinc-800 bg-zinc-950 text-zinc-100 p-1 min-w-36">
                                        <DropdownMenuItem
                                            onClick={() => setTheme('light')}
                                            className="flex items-center justify-between cursor-pointer rounded-lg px-2.5 py-1.5 text-xs text-zinc-300 hover:text-white hover:bg-zinc-900 focus:bg-zinc-900 focus:text-white"
                                        >
                                            <div className="flex items-center gap-2">
                                                <Sun className="size-3.5 text-amber-500" />
                                                <span>Terang</span>
                                            </div>
                                            {theme === 'light' && <Check className="size-3 text-emerald-400" />}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            onClick={() => setTheme('dark')}
                                            className="flex items-center justify-between cursor-pointer rounded-lg px-2.5 py-1.5 text-xs text-zinc-300 hover:text-white hover:bg-zinc-900 focus:bg-zinc-900 focus:text-white"
                                        >
                                            <div className="flex items-center gap-2">
                                                <Moon className="size-3.5 text-sky-400" />
                                                <span>Gelap</span>
                                            </div>
                                            {theme === 'dark' && <Check className="size-3 text-emerald-400" />}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            onClick={() => setTheme('system')}
                                            className="flex items-center justify-between cursor-pointer rounded-lg px-2.5 py-1.5 text-xs text-zinc-300 hover:text-white hover:bg-zinc-900 focus:bg-zinc-900 focus:text-white"
                                        >
                                            <div className="flex items-center gap-2">
                                                <Monitor className="size-3.5 text-zinc-400" />
                                                <span>Sistem</span>
                                            </div>
                                            {theme === 'system' && <Check className="size-3 text-emerald-400" />}
                                        </DropdownMenuItem>
                                    </DropdownMenuSubContent>
                                </DropdownMenuSub>

                                <DropdownMenuSeparator className="my-1 bg-zinc-800" />
                                <DropdownMenuItem
                                    onClick={handleLogout}
                                    className="rounded-lg cursor-pointer text-xs py-2 gap-2 text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 focus:bg-rose-500/10 focus:text-rose-300"
                                >
                                    <LogOut className="size-4" />
                                    Keluar
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
