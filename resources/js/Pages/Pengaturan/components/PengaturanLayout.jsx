import { Head, Link, usePage } from '@inertiajs/react';
import { Settings, Building, PlugZap } from 'lucide-react';
import { cn } from '@/lib/utils';
import DashboardLayout from '@/Layouts/DashboardLayout';

const tabs = [
    { label: 'Profil Aplikasi', href: '/pengaturan/profil', icon: Building },
    { label: 'Integrasi API', href: '/pengaturan/integrasi', icon: PlugZap },
];

export function PengaturanLayout({ title, children }) {
    const { url } = usePage();
    const currentPath = (url || '').split('?')[0];

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Manajemen', href: '#' },
        { label: 'Pengaturan', href: '/pengaturan' },
        { label: title, href: '#' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title={`${title} - Pengaturan`} />

            <div className="flex flex-col gap-4 w-full">
                <div>
                    <h1 className="text-xl font-bold tracking-tight flex items-center gap-2">
                        <Settings className="size-5 text-primary" />
                        Pengaturan Aplikasi
                    </h1>
                    <p className="text-xs text-muted-foreground mt-0.5">
                        Kelola identitas aplikasi dan konfigurasi integrasi layanan eksternal.
                    </p>
                </div>

                <nav className="flex gap-1 border-b overflow-x-auto">
                    {tabs.map((tab) => {
                        const active = currentPath.startsWith(tab.href);
                        return (
                            <Link
                                key={tab.href}
                                href={tab.href}
                                className={cn(
                                    'flex items-center gap-1.5 px-3 py-2 text-sm font-medium border-b-2 -mb-px whitespace-nowrap transition-colors',
                                    active
                                        ? 'border-primary text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground'
                                )}
                            >
                                <tab.icon className="size-4" />
                                {tab.label}
                            </Link>
                        );
                    })}
                </nav>

                {children}
            </div>
        </DashboardLayout>
    );
}
