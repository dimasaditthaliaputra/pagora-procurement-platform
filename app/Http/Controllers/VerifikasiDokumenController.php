<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Verifikasi\VerifikasiDokumenRequest;
use App\Services\VerifikasiDokumenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class VerifikasiDokumenController extends Controller
{
    public function __construct(
        protected VerifikasiDokumenService $service
    ) {}

    /**
     * Display the list of vendor legal documents.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status_verifikasi' => [
                'nullable',
                'string',
                'in:pending,approved,rejected',
            ],
        ]);

        $documents = $this->service->paginate($filters);

        $documents->through(
            fn ($document) => [
                'uuid' => $document->uuid,
                'jenis_dokumen' => $document->jenis_dokumen,
                'nomor_dokumen' => $document->nomor_dokumen,
                'status_verifikasi' => $document->status_verifikasi,
                'alasan_penolakan' => $document->alasan_penolakan,
                'diverifikasi_pada' => $document->diverifikasi_pada,
                'file_url' => route(
                    'verifikasi-dokumen.download',
                    $document->uuid
                ),
                'vendor' => $document->vendor ? [
                    'uuid' => $document->vendor->uuid,
                    'nama_perusahaan' => $document->vendor->nama_perusahaan,
                    'email_kontak' => $document->vendor->email_kontak,
                ] : null,
            ]
        );

        return Inertia::render(
            'Superadmin/VerifikasiDokumen/Index',
            [
                'documents' => $documents,
                'filters' => $filters,
            ]
        );
    }

    /**
     * Verify a vendor legal document.
     */
    public function verify(
        VerifikasiDokumenRequest $request,
        string $uuid
    ) {
        try {
            $document = $this->service->verify(
                $uuid,
                $request->validated('aksi'),
                (int) $request->user()->getKey(),
                $request->validated('alasan_penolakan')
            );

            return ResponseHelper::success(
                $document->status_verifikasi === 'approved'
                    ? 'Dokumen legalitas berhasil disetujui.'
                    : 'Dokumen legalitas ditolak.',
                [
                    'uuid' => $document->uuid,
                    'status_verifikasi' => $document->status_verifikasi,
                    'alasan_penolakan' => $document->alasan_penolakan,
                ],
                route('verifikasi-dokumen.index'),
                $request
            );
        } catch (Throwable $exception) {
            if (
                $exception instanceof
                \Illuminate\Database\Eloquent\ModelNotFoundException
            ) {
                return ResponseHelper::notFound(
                    'Dokumen legalitas tidak ditemukan.',
                    null,
                    $request
                );
            }

            if (
                $exception instanceof
                \Illuminate\Validation\ValidationException
            ) {
                throw $exception;
            }

            return ResponseHelper::serverError(
                'Gagal memproses verifikasi dokumen legalitas.',
                $exception,
                $request
            );
        }
    }

    /**
     * Stream a private legal document to an authorized user.
     */
    public function download(string $uuid): StreamedResponse
    {
        $document = $this->service->findByUuid($uuid);
        $path = $document->file_url;

        abort_unless(
            is_string($path)
            && $path !== ''
            && Storage::disk('local')->exists($path),
            404,
            'File dokumen legalitas tidak ditemukan.'
        );

        return Storage::disk('local')->download(
            $path,
            basename($path)
        );
    }
}
