import { Head, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import DashboardOverview from '@/Components/DashboardOverview';
import SuperadminDashboard from '@/Pages/Dashboard/SuperadminDashboard';

export default function Dashboard({
    user,
    is_superadmin = false,
    superadmin_dashboard = null,
}) {
    const { auth } = usePage().props;
    const isSuperadminRole =
        Boolean(is_superadmin) || auth?.role?.name === 'superadmin';

    return (
        <DashboardLayout>
            <Head
                title={
                    isSuperadminRole
                        ? 'Dashboard Superadmin - PAGORA'
                        : 'Dashboard - PAGORA'
                }
            />

            {isSuperadminRole && superadmin_dashboard ? (
                <SuperadminDashboard
                    userName={user?.name}
                    dashboard={superadmin_dashboard}
                />
            ) : (
                <DashboardOverview userName={user?.name} />
            )}
        </DashboardLayout>
    );
}
