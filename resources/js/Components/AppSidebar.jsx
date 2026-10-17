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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import {
    LayoutDashboard,
    ShoppingCart,
    Package,
    Users,
    FileText,
    Settings,
    ChevronUp,
    LogOut,
    UserCircle,
    Building2,
    ClipboardCheck,
    BarChart3,
    Bell,
    Truck,
    GalleryVerticalEnd,
    Boxes,
    TrendingUp,
    ShieldCheck,
} from 'lucide-react';

const mainNavItems = [
    { title: 'Dashboard', icon: LayoutDashboard, href: '/dashboard', active: true },
    { title: 'Pengadaan', icon: ShoppingCart, href: '#' },
    { title: 'Katalog Produk', icon: Package, href: '#' },
    { title: 'Vendor', icon: Building2, href: '#' },
    { title: 'Purchase Order', icon: FileText, href: '#' },
    { title: 'Pengiriman', icon: Truck, href: '#' },
];

const masterNavItems = [
    { title: 'Komoditas', icon: Boxes, href: '/master/komoditas' },
    { title: 'Harga Referensi & Pagu', icon: TrendingUp, href: '#' },
    { title: 'Data Vendor', icon: Building2, href: '#' },
    { title: 'Role & Akses', icon: ShieldCheck, href: '#' },
];

const managementNavItems = [
    { title: 'Inspeksi QC', icon: ClipboardCheck, href: '#' },
    { title: 'Laporan', icon: BarChart3, href: '#' },
    { title: 'Notifikasi', icon: Bell, href: '#' },
    { title: 'Pengguna', icon: Users, href: '#' },
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

export function AppSidebar({ ...props }) {
    const { user } = usePage().props;

    const handleLogout = () => {
        router.post('/logout');
    };

    return (
        <Sidebar collapsible="icon" {...props}>
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild className="data-[slot=sidebar-menu-button]:!p-1.5">
                            <Link href="/dashboard">
                                <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                    <GalleryVerticalEnd className="size-4" />
                                </div>
                                <div className="grid flex-1 text-left text-sm leading-tight">
                                    <span className="truncate font-semibold">PAGORA</span>
                                    <span className="truncate text-xs text-muted-foreground">E-Procurement</span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup>
                    <SidebarGroupLabel>Menu Utama</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {mainNavItems.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton asChild isActive={item.active}>
                                        <Link href={item.href}>
                                            <item.icon />
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>

                <SidebarGroup>
                    <SidebarGroupLabel>Master Data</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {masterNavItems.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton asChild>
                                        <Link href={item.href}>
                                            <item.icon />
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>

                <SidebarGroup>
                    <SidebarGroupLabel>Manajemen</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {managementNavItems.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton asChild>
                                        <Link href={item.href}>
                                            <item.icon />
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <SidebarMenuButton
                                    size="lg"
                                    className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                                >
                                    <Avatar className="h-8 w-8 rounded-lg">
                                        <AvatarImage src={user?.avatar} alt={user?.nama_lengkap} />
                                        <AvatarFallback className="rounded-lg">
                                            {getInitials(user?.nama_lengkap)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="grid flex-1 text-left text-sm leading-tight">
                                        <span className="truncate font-semibold">{user?.nama_lengkap ?? 'Pengguna'}</span>
                                        <span className="truncate text-xs text-muted-foreground">{user?.email}</span>
                                    </div>
                                    <ChevronUp className="ml-auto size-4" />
                                </SidebarMenuButton>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                className="w-[--radix-dropdown-menu-trigger-width] min-w-56 rounded-lg"
                                side="top"
                                align="end"
                                sideOffset={4}
                            >
                                <DropdownMenuLabel className="p-0 font-normal">
                                    <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                        <Avatar className="h-8 w-8 rounded-lg">
                                            <AvatarImage src={user?.avatar} alt={user?.nama_lengkap} />
                                            <AvatarFallback className="rounded-lg">
                                                {getInitials(user?.nama_lengkap)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div className="grid flex-1 text-left text-sm leading-tight">
                                            <span className="truncate font-semibold">{user?.nama_lengkap}</span>
                                            <span className="truncate text-xs text-muted-foreground">{getRoleName(user?.role_id)}</span>
                                        </div>
                                    </div>
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem>
                                    <UserCircle className="mr-2 size-4" />
                                    Profil Saya
                                </DropdownMenuItem>
                                <DropdownMenuItem>
                                    <Settings className="mr-2 size-4" />
                                    Pengaturan
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem onClick={handleLogout} className="text-destructive focus:text-destructive">
                                    <LogOut className="mr-2 size-4" />
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
