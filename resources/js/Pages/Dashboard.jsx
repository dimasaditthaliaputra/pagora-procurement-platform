import { Head, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import DashboardOverview from '@/Components/DashboardOverview';
import ManajemenDashboard from '@/Pages/Dashboard/ManajemenDashboard';
import OwnerDashboard from '@/Pages/Dashboard/OwnerDashboard';
import SuperadminDashboard from '@/Pages/Dashboard/SuperadminDashboard';
import BuyerDashboard from '@/Pages/Dashboard/BuyerDashboard';
import QcDashboard from '@/Pages/Dashboard/QcDashboard';

export default function Dashboard({
    user,
    is_superadmin = false,
    superadmin_dashboard = null,
    is_manajemen = false,
    manajemen_dashboard = null,
    is_owner = false,
    owner_dashboard = null,
    is_purchasing = false,
    buyer_dashboard = null,
    is_qc_gudang = false,
    qc_dashboard = null,
}) {
    const { auth } = usePage().props;
    const roleName = auth?.role?.name;
    const isSuperadminRole = Boolean(is_superadmin) || roleName === 'superadmin';
    const isManajemenRole = Boolean(is_manajemen) || roleName === 'manajemen';
    const isOwnerRole = Boolean(is_owner) || roleName === 'owner';
    const isPurchasingRole = Boolean(is_purchasing) || roleName === 'purchasing';
    const isQcGudangRole = Boolean(is_qc_gudang) || roleName === 'qc_gudang';

    const pageTitle = isSuperadminRole
        ? 'Dashboard Superadmin - PAGORA'
        : isManajemenRole
          ? 'Dashboard Manajemen - PAGORA'
          : isOwnerRole
            ? 'Dashboard Owner - PAGORA'
            : isPurchasingRole
              ? 'Dashboard Purchasing - PAGORA'
              : isQcGudangRole
                ? 'Dashboard QC Gudang - PAGORA'
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
            ) : isOwnerRole && owner_dashboard ? (
                <OwnerDashboard
                    userName={user?.name}
                    dashboard={owner_dashboard}
                />
            ) : isPurchasingRole && buyer_dashboard ? (
                <BuyerDashboard
                    userName={user?.name}
                    dashboard={buyer_dashboard}
                />
            ) : isQcGudangRole && qc_dashboard ? (
                <QcDashboard
                    userName={user?.name}
                    dashboard={qc_dashboard}
                />
            ) : (
                <DashboardOverview userName={user?.name} />
            )}
        </DashboardLayout>
    );
}
