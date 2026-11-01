<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\DokumenLegalitasVendor;
use App\Models\Vendor;
use App\Repositories\Contracts\DokumenLegalitasVendorRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class DokumenLegalitasVendorRepository implements DokumenLegalitasVendorRepositoryInterface
{
    public function __construct(
        protected DokumenLegalitasVendor $model
    ) {}

    /**
     * Get paginated vendors that submitted legal documents, each with all of its documents.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateVendors(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return Vendor::query()
            ->whereHas('legalDocuments')
            ->with(['legalDocuments' => fn ($query) => $query->orderBy('id'), 'legalDocuments.verifier'])
            ->when(
                ! empty($filters['verification_status']) && $filters['verification_status'] !== 'all',
                fn ($query) => $query->where('verification_status', $filters['verification_status'])
            )
            ->when(
                ! empty($filters['search']),
                fn ($query) => $query->where(function ($q) use ($filters) {
                    $term = '%'.$filters['search'].'%';
                    $q->where('company_name', 'like', $term)
                        ->orWhere('contact_email', 'like', $term)
                        ->orWhere('kategori_komoditas', 'like', $term)
                        ->orWhereHas('legalDocuments', fn ($dq) => $dq->where('document_number', 'like', $term)
                            ->orWhere('document_type', 'like', $term));
                })
            )
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Get every legal document of a vendor.
     *
     * @return Collection<int, DokumenLegalitasVendor>
     */
    public function getByVendorId(int $vendorId): Collection
    {
        return $this->model->newQuery()->where('vendor_id', $vendorId)->get();
    }

    public function findByUuid(string $uuid): ?DokumenLegalitasVendor
    {
        return $this->model
            ->newQuery()
            ->with('vendor')
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Create a new vendor legal document record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DokumenLegalitasVendor
    {
        return $this->model->newQuery()->create($data);
    }

    /**
     * Save changes to a legal document.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(
        DokumenLegalitasVendor $document,
        array $data
    ): bool {
        return $document->update($data);
    }
}
