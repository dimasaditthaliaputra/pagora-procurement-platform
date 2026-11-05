import DashboardLayout from '@/Layouts/DashboardLayout';
import { Head, Link } from '@inertiajs/react';

export default function Index({ invitations }) {
    const items = invitations?.data ?? [];

    const getBuyerName = (invitation) => {
        const buyer = invitation.perusahaan_buyer ?? {};

        return buyer.nama_perusahaan
            ?? buyer.name
            ?? buyer.nama
            ?? 'Perusahaan Buyer';
    };

    return (
        <DashboardLayout>
            <Head title="Undangan Kerja Sama Masuk" />

            <div className="space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold">
                        Undangan Kerja Sama Masuk
                    </h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Daftar undangan kerja sama yang dikirim oleh perusahaan Buyer.
                    </p>
                </div>

                <div className="rounded-lg border bg-white">
                    {items.length === 0 ? (
                        <div className="p-8 text-center text-gray-500">
                            Belum ada undangan kerja sama masuk.
                        </div>
                    ) : (
                        <div className="divide-y">
                            {items.map((invitation) => (
                                <div
                                    key={invitation.uuid}
                                    className="flex flex-col justify-between gap-4 p-5 sm:flex-row sm:items-center"
                                >
                                    <div>
                                        <h2 className="font-semibold text-gray-900">
                                            {getBuyerName(invitation)}
                                        </h2>

                                        <p className="mt-1 text-sm text-gray-500">
                                            Diterima:{' '}
                                            {invitation.created_at
                                                ? new Date(invitation.created_at).toLocaleDateString('id-ID')
                                                : '-'}
                                        </p>

                                        <span className="mt-2 inline-block rounded-full bg-yellow-100 px-3 py-1 text-xs font-medium text-yellow-800">
                                            Menunggu Tanggapan
                                        </span>
                                    </div>

                                    <Link
                                        href={`/kerja-sama/undangan-masuk/${invitation.uuid}`}
                                        className="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                                    >
                                        Lihat Undangan
                                    </Link>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {invitations?.links?.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                        {invitations.links.map((link, index) => (
                            <Link
                                key={index}
                                href={link.url ?? '#'}
                                preserveScroll
                                className={`rounded border px-3 py-2 text-sm ${
                                    link.active
                                        ? 'border-blue-600 bg-blue-600 text-white'
                                        : 'border-gray-300 bg-white text-gray-700'
                                } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}