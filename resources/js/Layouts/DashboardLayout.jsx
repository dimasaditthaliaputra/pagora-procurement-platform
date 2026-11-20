import { Fragment } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { ChartLine, LayoutDashboard } from 'lucide-react';
import { SidebarInset, SidebarProvider, SidebarTrigger } from '@/Components/ui/sidebar';
import { TooltipProvider } from '@/Components/ui/tooltip';
import { Separator } from '@/Components/ui/separator';
import { AppSidebar } from '@/Components/AppSidebar';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';
import { FlashHandler } from '@/Components/flash-handler';
import { ThemeToggle } from '@/Components/ThemeToggle';
import { cn } from '@/lib/utils';

export default function DashboardLayout({ children, breadcrumbs = [] }) {
    const { props, url } = usePage();
    const auth = props?.auth;
    const roleName = auth?.role?.name;
    const canViewAnalitik =
        Boolean(auth?.can?.['harga_pagu.view_any']) ||
        roleName === 'owner' ||
        roleName === 'purchasing';
    const currentPath = (url || '').split('?')[0].split('#')[0];
    const isDashboardTabPage =
        currentPath === '/dashboard' || currentPath.startsWith('/analitik-harga');
    const showDashboardHeaderTabs = isDashboardTabPage && canViewAnalitik;

    return (
        <TooltipProvider>
            <FlashHandler />
            <SidebarProvider>
                <AppSidebar />
                <SidebarInset>
                    <header className="flex h-16 shrink-0 items-center gap-2 border-b border-border/40 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
                        <div className="flex w-full items-center justify-between px-4">
                            <div className="flex items-center gap-2">
                                <SidebarTrigger className="-ml-1" />
                                <Separator orientation="vertical" className="mr-2 data-[orientation=vertical]:h-4" />
                                {showDashboardHeaderTabs ? (
                                    <nav
                                        aria-label="Navigasi Tab Dashboard"
                                        className="inline-flex items-center gap-1 rounded-lg border border-border/60 bg-muted/50 p-1"
                                    >
                                        <Link
                                            href="/dashboard"
                                            className={cn(
                                                'inline-flex items-center gap-1.5 rounded-md px-3 py-1 text-xs font-medium transition-all whitespace-nowrap',
                                                currentPath === '/dashboard'
                                                    ? 'bg-background text-foreground shadow-2xs font-semibold'
                                                    : 'text-muted-foreground hover:text-foreground',
                                            )}
                                        >
                                            <LayoutDashboard className="size-3.5 shrink-0" />
                                            <span>Dashboard</span>
                                        </Link>
                                        <Link
                                            href="/analitik-harga"
                                            className={cn(
                                                'inline-flex items-center gap-1.5 rounded-md px-3 py-1 text-xs font-medium transition-all whitespace-nowrap',
                                                currentPath.startsWith('/analitik-harga')
                                                    ? 'bg-background text-foreground shadow-2xs font-semibold'
                                                    : 'text-muted-foreground hover:text-foreground',
                                            )}
                                        >
                                            <ChartLine className="size-3.5 shrink-0" />
                                            <span>Dashboard Analitik</span>
                                        </Link>
                                    </nav>
                                ) : (
                                    <Breadcrumb>
                                        <BreadcrumbList>
                                            {breadcrumbs.length === 0 ? (
                                                <BreadcrumbItem>
                                                    <BreadcrumbPage>Dashboard</BreadcrumbPage>
                                                </BreadcrumbItem>
                                            ) : (
                                                breadcrumbs.map((crumb, index) => (
                                                    // Separator and item are both <li>: siblings, never nested.
                                                    <Fragment key={crumb.label}>
                                                        {index > 0 && <BreadcrumbSeparator />}
                                                        <BreadcrumbItem>
                                                            {index === breadcrumbs.length - 1 ? (
                                                                <BreadcrumbPage>{crumb.label}</BreadcrumbPage>
                                                            ) : (
                                                                <BreadcrumbLink href={crumb.href}>{crumb.label}</BreadcrumbLink>
                                                            )}
                                                        </BreadcrumbItem>
                                                    </Fragment>
                                                ))
                                            )}
                                        </BreadcrumbList>
                                    </Breadcrumb>
                                )}
                            </div>
                        </div>
                    </header>
                    <div className="flex flex-1 flex-col gap-4 p-4 pt-4">
                        {children}
                    </div>
                </SidebarInset>
            </SidebarProvider>
        </TooltipProvider>
    );
}
