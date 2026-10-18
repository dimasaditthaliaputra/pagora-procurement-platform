import { router } from '@inertiajs/react';

export default function Index({ documents, filters }) {
    const items = documents?.data ?? [];

    function verifyDocument(uuid, aksi) {
        let alasan_penolakan = '';

        if (aksi === 'reject') {
            alasan_penolakan = window.prompt(
                'Masukkan alasan penolakan dokumen:'
            );

            if (
                alasan_penolakan === null ||
                alasan_penolakan.trim() === ''
            ) {
                window.alert('Alasan penolakan wajib diisi.');
                return;
            }
        }

        const message =
            aksi === 'approve'
                ? 'Apakah kamu yakin ingin menyetujui dokumen ini?'
                : 'Apakah kamu yakin ingin menolak dokumen ini?';

        if (!window.confirm(message)) {
            return;
        }

        router.post(
            `/superadmin/verifikasi-dokumen/${uuid}/verifikasi`,
            {
                aksi,
                alasan_penolakan,
            },
            {
                preserveScroll: true,
            }
        );
    }

    function filterDocuments(event) {
        const status = event.target.value;

        router.get(
            '/superadmin/verifikasi-dokumen',
            status
                ? { status_verifikasi: status }
                : {},
            {
                preserveState: true,
                replace: true,
            }
        );
    }

    return (
        <div className="min-h-screen bg-gray-50 p-6">
            <div className="mx-auto max-w-6xl">
                <h1 className="text-2xl font-bold text-gray-800">
                    Verifikasi Legalitas Vendor
                </h1>

                <p className="mt-2 text-sm text-gray-600">
                    Periksa dokumen legalitas vendor sebelum
                    memberikan persetujuan.
                </p>

                <div className="my-6">
                    <label className="mb-2 block font-medium">
                        Filter Status
                    </label>

                    <select
                        value={filters?.status_verifikasi ?? ''}
                        onChange={filterDocuments}
                        className="rounded-lg border border-gray-300 bg-white p-2"
                    >
                        <option value="">Semua status</option>
                        <option value="pending">Menunggu</option>
                        <option value="approved">Disetujui</option>
                        <option value="rejected">Ditolak</option>
                    </select>
                </div>

                <div className="overflow-x-auto rounded-xl border bg-white">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-gray-100">
                            <tr>
                                <th className="p-3">Vendor</th>
                                <th className="p-3">Jenis Dokumen</th>
                                <th className="p-3">Nomor Dokumen</th>
                                <th className="p-3">Status</th>
                                <th className="p-3">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            {items.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan="5"
                                        className="p-6 text-center text-gray-500"
                                    >
                                        Belum ada dokumen legalitas.
                                    </td>
                                </tr>
                            ) : (
                                items.map((document) => (
                                    <tr
                                        key={document.uuid}
                                        className="border-t"
                                    >
                                        <td className="p-3">
                                            <div className="font-medium">
                                                {document.vendor?.nama_perusahaan
                                                    ?? 'Vendor tidak ditemukan'}
                                            </div>

                                            <div className="text-gray-500">
                                                {document.vendor?.email_kontak
                                                    ?? '-'}
                                            </div>
                                        </td>

                                        <td className="p-3">
                                            {document.jenis_dokumen}
                                        </td>

                                        <td className="p-3">
                                            {document.nomor_dokumen || '-'}
                                        </td>

                                        <td className="p-3">
                                            <span className="capitalize">
                                                {document.status_verifikasi}
                                            </span>

                                            {document.alasan_penolakan && (
                                                <p className="mt-1 text-red-600">
                                                    Alasan: {document.alasan_penolakan}
                                                </p>
                                            )}
                                        </td>

                                        <td className="space-y-2 p-3">
                                            <a
                                                href={document.file_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="block text-blue-600 underline"
                                            >
                                                Lihat Dokumen
                                            </a>

                                            {document.status_verifikasi === 'pending' && (
                                                <div className="flex flex-wrap gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            verifyDocument(
                                                                document.uuid,
                                                                'approve'
                                                            )
                                                        }
                                                        className="rounded bg-green-600 px-3 py-2 text-white hover:bg-green-700"
                                                    >
                                                        Setujui
                                                    </button>

                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            verifyDocument(
                                                                document.uuid,
                                                                'reject'
                                                            )
                                                        }
                                                        className="rounded bg-red-600 px-3 py-2 text-white hover:bg-red-700"
                                                    >
                                                        Tolak
                                                    </button>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="mt-4 flex items-center justify-between">
                    <span className="text-sm text-gray-600">
                        Total dokumen: {documents?.total ?? items.length}
                    </span>

                    <div className="flex flex-wrap gap-2">
                        {documents?.links?.map((link, index) => (
                            <button
                                key={index}
                                type="button"
                                disabled={!link.url}
                                onClick={() => {
                                    if (link.url) {
                                        router.visit(link.url);
                                    }
                                }}
                                className="rounded border bg-white px-3 py-1 disabled:opacity-40"
                                dangerouslySetInnerHTML={{
                                    __html: link.label,
                                }}
                            />
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
