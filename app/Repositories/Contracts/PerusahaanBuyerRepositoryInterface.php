<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\PerusahaanBuyer;
use Illuminate\Database\Eloquent\Collection;

interface PerusahaanBuyerRepositoryInterface
{
    /**
     * Find a buyer company by its UUID.
     */
    public function findByUuid(string $uuid): ?PerusahaanBuyer;

    /**
     * Retrieve all buyer companies ordered by name.
     *
     * @return Collection<int, PerusahaanBuyer>
     */
    public function getAllOrdered(): Collection;

    /**
     * Create a new buyer company record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PerusahaanBuyer;
}
