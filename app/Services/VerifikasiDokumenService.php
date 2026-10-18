<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DokumenLegalitasVendor;
use App\Repositories\Contracts\DokumenLegalitasVendorRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerifikasiDokumenService
{
    public function __construct(
        protected DokumenLegalitasVendorRepositoryInterface $repository
    ) {}

    /**
     * Get paginated legal documents.
     *
     * @param array<string, mixed> $filters
     */
    public function paginate(
        array $filters,
        int $perPage = 10
    ): LengthAwarePaginator {
        return $this->repository->paginate($filters, $perPage);
    }

    /**
     * Find a legal document by UUID.
     */
    public function findByUuid(string $uuid): DokumenLegalitasVendor
    {
        $document = $this->repository->findByUuid($uuid);

        if ($document === null) {
            throw (new ModelNotFoundException(
                'Dokumen legalitas tidak ditemukan.'
            ))->setModel(DokumenLegalitasVendor::class, [$uuid]);
        }

        return $document;
    }

    /**
     * Approve or reject a vendor legal document.
     */
    public function verify(
        string $uuid,
        string $action,
        int $verifierId,
        ?string $rejectionReason = null
    ): DokumenLegalitasVendor {
        if (!in_array($action, ['approve', 'reject'], true)) {
            throw ValidationException::withMessages([
                'aksi' => 'Aksi verifikasi tidak valid.',
            ]);
        }

        if (
            $action === 'reject'
            && trim((string) $rejectionReason) === ''
        ) {
            throw ValidationException::withMessages([
                'alasan_penolakan' => 'Alasan penolakan wajib diisi.',
            ]);
        }

        return DB::transaction(function () use (
            $uuid,
            $action,
            $verifierId,
            $rejectionReason
        ): DokumenLegalitasVendor {
            $document = $this->repository->findByUuid($uuid);

            if ($document === null) {
                throw (new ModelNotFoundException(
                    'Dokumen legalitas tidak ditemukan.'
                ))->setModel(DokumenLegalitasVendor::class, [$uuid]);
            }

            if ($document->status_verifikasi !== 'pending') {
                throw ValidationException::withMessages([
                    'status_verifikasi' =>
                        'Dokumen ini sudah diverifikasi dan tidak dapat diproses ulang.',
                ]);
            }

            $vendor = $document->vendor;

            if ($vendor === null) {
                throw (new ModelNotFoundException(
                    'Data vendor tidak ditemukan.'
                ))->setModel('Vendor');
            }

            $approved = $action === 'approve';

            $this->repository->update($document, [
                'status_verifikasi' => $approved ? 'approved' : 'rejected',
                'verif_by' => $verifierId,
                'diverifikasi_pada' => now(),
                'alasan_penolakan' => $approved
                    ? null
                    : trim((string) $rejectionReason),
            ]);

            $vendor->status_verifikasi = $approved
                ? 'approved'
                : 'rejected';

            $vendor->save();

            return $document->fresh(['vendor']);
        });
    }
}
