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
import { Toaster } from '@/Components/ui/sonner';
import { FlashHandler } from '@/Components/flash-handler';

export default function DashboardLayout({ children, breadcrumbs = [] }) {
    return (
        <TooltipProvider>
            <FlashHandler />
            <Toaster />
            <SidebarProvider>
                <AppSidebar />
                <SidebarInset>
                    <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
                        <div className="flex items-center gap-2 px-4">
                            <SidebarTrigger className="-ml-1" />
                            <Separator orientation="vertical" className="mr-2 data-[orientation=vertical]:h-4" />
                            <Breadcrumb>
                                <BreadcrumbList>
                                    {breadcrumbs.length === 0 ? (
                                        <BreadcrumbItem>
                                            <BreadcrumbPage>Dashboard</BreadcrumbPage>
                                        </BreadcrumbItem>
                                    ) : (
                                        breadcrumbs.map((crumb, index) => (
                                            <BreadcrumbItem key={crumb.label}>
                                                {index > 0 && <BreadcrumbSeparator />}
                                                {index === breadcrumbs.length - 1 ? (
                                                    <BreadcrumbPage>{crumb.label}</BreadcrumbPage>
                                                ) : (
                                                    <BreadcrumbLink href={crumb.href}>{crumb.label}</BreadcrumbLink>
                                                )}
                                            </BreadcrumbItem>
                                        ))
                                    )}
                                </BreadcrumbList>
                            </Breadcrumb>
                        </div>
                    </header>
                    <div className="flex flex-1 flex-col gap-4 p-4 pt-0">
                        {children}
                    </div>
                </SidebarInset>
            </SidebarProvider>
        </TooltipProvider>
    );
}
