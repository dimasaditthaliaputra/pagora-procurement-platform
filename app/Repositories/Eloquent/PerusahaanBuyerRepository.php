<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\PerusahaanBuyer;
use App\Repositories\Contracts\PerusahaanBuyerRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

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
            ->select(['id', 'uuid', 'nama_perusahaan', 'status_aktif'])
            ->orderBy('nama_perusahaan')
            ->get();
    }
}
