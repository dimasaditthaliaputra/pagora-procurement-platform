import DashboardLayout from '@/Layouts/DashboardLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Show({ invitation }) {
    const [rejectionReason, setRejectionReason] = useState('');
    const [showRejectForm, setShowRejectForm] = useState(false);
    const [processing, setProcessing] = useState(false);

    const buyer = invitation.perusahaan_buyer ?? {};

    const buyerName =
        buyer.nama_perusahaan
        ?? buyer.name
        ?? buyer.nama
        ?? 'Perusahaan Buyer';

    const respondUrl =
        `/kerja-sama/undangan-masuk/${invitation.uuid}/tanggapi`;

    const acceptInvitation = () => {
        if (processing) return;

        setProcessing(true);

        router.post(
            respondUrl,
            { action: 'accept' },
            {
                onFinish: () => setProcessing(false),
            }
        );
    };

    const rejectInvitation = (event) => {
        event.preventDefault();

        if (!rejectionReason.trim() || processing) return;

        setProcessing(true);

        router.post(
            respondUrl,
            {
                action: 'reject',
                rejection_reason: rejectionReason,
            },
            {
                onFinish: () => setProcessing(false),
            }
        );
    };

    return (
        <DashboardLayout>
            <Head title="Detail Undangan Kerja Sama" />

            <div className="max-w-3xl space-y-6 p-6">
                <div>
                    <Link
                        href="/kerja-sama/undangan-masuk"
                        className="text-sm text-blue-600 hover:underline"
                    >
                        ← Kembali ke Undangan Masuk
                    </Link>

                    <h1 className="mt-4 text-2xl font-bold">
                        Detail Undangan Kerja Sama
                    </h1>
                </div>

                <div className="space-y-4 rounded-lg border bg-white p-6">
                    <div>
                        <p className="text-sm text-gray-500">
                            Perusahaan yang mengundang
                        </p>
                        <h2 className="mt-1 text-xl font-semibold">
                            {buyerName}
                        </h2>
                    </div>

                    <div className="border-t pt-4">
                        <p className="text-sm text-gray-500">
                            Tanggal Undangan
                        </p>
                        <p className="mt-1 font-medium">
                            {invitation.created_at
                                ? new Date(invitation.created_at).toLocaleDateString('id-ID')
                                : '-'}
                        </p>
                    </div>

                    <div className="border-t pt-4">
                        <p className="text-sm text-gray-500">
                            Status
                        </p>
                        <span className="mt-2 inline-block rounded-full bg-yellow-100 px-3 py-1 text-sm font-medium text-yellow-800">
                            Menunggu Tanggapan
                        </span>
                    </div>
                </div>

                <div className="rounded-lg border bg-white p-6">
                    <h2 className="font-semibold">
                        Tanggapan Undangan
                    </h2>

                    <p className="mt-1 text-sm text-gray-500">
                        Pilih apakah kamu menerima atau menolak undangan kerja sama ini.
                    </p>

                    <div className="mt-5 flex flex-wrap gap-3">
                        <button
                            type="button"
                            onClick={acceptInvitation}
                            disabled={processing}
                            className="rounded-md bg-green-600 px-5 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
                        >
                            {processing ? 'Memproses...' : 'Terima Undangan'}
                        </button>

                        <button
                            type="button"
                            onClick={() => setShowRejectForm(!showRejectForm)}
                            disabled={processing}
                            className="rounded-md bg-red-600 px-5 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        >
                            Tolak Undangan
                        </button>
                    </div>

                    {showRejectForm && (
                        <form onSubmit={rejectInvitation} className="mt-5 space-y-3">
                            <label
                                htmlFor="rejection_reason"
                                className="block text-sm font-medium"
                            >
                                Alasan Penolakan
                            </label>

                            <textarea
                                id="rejection_reason"
                                value={rejectionReason}
                                onChange={(event) => setRejectionReason(event.target.value)}
                                maxLength={1000}
                                required
                                rows={4}
                                placeholder="Tuliskan alasan penolakan..."
                                className="w-full rounded-md border border-gray-300 p-3 text-sm focus:border-blue-500 focus:outline-none"
                            />

                            <button
                                type="submit"
                                disabled={processing || !rejectionReason.trim()}
                                className="rounded-md bg-red-600 px-5 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                            >
                                {processing ? 'Memproses...' : 'Kirim Penolakan'}
                            </button>
                        </form>
                    )}
                </div>
            </div>
        </DashboardLayout>
    );
}