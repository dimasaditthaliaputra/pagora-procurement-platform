import { Head } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import DashboardOverview from '@/Components/DashboardOverview';

export default function Dashboard({ user }) {
    return (
        <DashboardLayout>
            <Head title="Dashboard — PAGORA" />

            <DashboardOverview userName={user?.name} />
        </DashboardLayout>
    );
}
