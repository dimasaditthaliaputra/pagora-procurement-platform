<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\PerusahaanBuyer;
use App\Repositories\Contracts\PerusahaanBuyerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PerusahaanBuyerRepository implements PerusahaanBuyerRepositoryInterface
{
    public function __construct(
        private readonly PerusahaanBuyer $model
    ) {}

    /**
     * Find a buyer company by its UUID.
     */
    public function findByUuid(string $uuid): ?PerusahaanBuyer
    {
        return $this->model->newQuery()->where('uuid', $uuid)->first();
    }

    /**
     * Retrieve all buyer companies ordered by name.
     *
     * @return Collection<int, PerusahaanBuyer>
     */
    public function getAllOrdered(): Collection
    {
        return $this->model->newQuery()
            ->select(['id', 'uuid', 'company_name', 'is_active'])
            ->orderBy('company_name')
            ->get();
    }

    /**
     * Create a new buyer company record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PerusahaanBuyer
    {
        return $this->model->newQuery()->create($data);
    }

    /**
     * Update a buyer company record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(PerusahaanBuyer $tenant, array $data): bool
    {
        return $tenant->update($data);
    }

    /**
     * Paginate tenant companies with search and verification status filter.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        $status = isset($filters['verification_status']) ? trim((string) $filters['verification_status']) : 'all';

        $query = $this->model->newQuery()
            ->with(['users' => function ($q) {
                $q->whereHas('role', fn ($roleQuery) => $roleQuery->where('name', 'owner'));
            }, 'verifiedByUser'])
            ->when($search !== '', function ($q) use ($search) {
                $driver = DB::getDriverName();
                $op = $driver === 'pgsql' ? 'ilike' : 'like';

                $q->where(function ($sub) use ($search, $op) {
                    $sub->where('company_name', $op, "%{$search}%")
                        ->orWhere('tax_number', $op, "%{$search}%")
                        ->orWhere('address', $op, "%{$search}%")
                        ->orWhereHas('users', function ($userSub) use ($search, $op) {
                            $userSub->where('name', $op, "%{$search}%")
                                ->orWhere('email', $op, "%{$search}%");
                        });
                });
            })
            ->when($status !== 'all' && in_array($status, ['pending', 'approved', 'rejected'], true), function ($q) use ($status) {
                $q->where('verification_status', $status);
            })
            ->orderByRaw("CASE WHEN verification_status = 'pending' THEN 0 WHEN verification_status = 'rejected' THEN 1 ELSE 2 END")
            ->latest('created_at');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Get aggregate verification status counts.
     *
     * @return array{total: int, pending: int, approved: int, rejected: int}
     */
    public function getStatusCounts(): array
    {
        $raw = $this->model->newQuery()
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN verification_status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected
            ")
            ->first();

        return [
            'total' => (int) ($raw?->total ?? 0),
            'pending' => (int) ($raw?->pending ?? 0),
            'approved' => (int) ($raw?->approved ?? 0),
            'rejected' => (int) ($raw?->rejected ?? 0),
        ];
    }
}
