import { Head, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import DashboardOverview from '@/Components/DashboardOverview';
import ManajemenDashboard from '@/Pages/Dashboard/ManajemenDashboard';
import SuperadminDashboard from '@/Pages/Dashboard/SuperadminDashboard';

export default function Dashboard({
    user,
    is_superadmin = false,
    superadmin_dashboard = null,
    is_manajemen = false,
    manajemen_dashboard = null,
}) {
    const { auth } = usePage().props;
    const roleName = auth?.role?.name;
    const isSuperadminRole = Boolean(is_superadmin) || roleName === 'superadmin';
    const isManajemenRole = Boolean(is_manajemen) || roleName === 'manajemen';

    const pageTitle = isSuperadminRole
        ? 'Dashboard Superadmin - PAGORA'
        : isManajemenRole
          ? 'Dashboard Manajemen - PAGORA'
          : 'Dashboard - PAGORA';

    return (
        <DashboardLayout>
            <Head title={pageTitle} />

            {isSuperadminRole && superadmin_dashboard ? (
                <SuperadminDashboard
                    userName={user?.name}
                    dashboard={superadmin_dashboard}
                />
            ) : isManajemenRole && manajemen_dashboard ? (
                <ManajemenDashboard
                    userName={user?.name}
                    dashboard={manajemen_dashboard}
                />
            ) : (
                <DashboardOverview userName={user?.name} />
            )}
        </DashboardLayout>
    );
}
