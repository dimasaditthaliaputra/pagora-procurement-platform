<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Pengaturan;
use Illuminate\Support\Collection;

interface PengaturanRepositoryInterface
{
    /**
     * Get all settings keyed by their key.
     *
     * @return Collection<string, Pengaturan>
     */
    public function allKeyed(): Collection;

    /**
     * Insert or update a setting by its key.
     *
     * @param  array<string, mixed>  $data
     */
    public function upsert(string $key, array $data): Pengaturan;
}
