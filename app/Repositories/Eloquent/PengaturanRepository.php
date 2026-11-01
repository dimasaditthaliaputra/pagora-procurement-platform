<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Pengaturan;
use App\Repositories\Contracts\PengaturanRepositoryInterface;
use Illuminate\Support\Collection;

class PengaturanRepository implements PengaturanRepositoryInterface
{
    public function __construct(
        protected Pengaturan $model
    ) {}

    /**
     * Get all settings keyed by their key.
     *
     * @return Collection<string, Pengaturan>
     */
    public function allKeyed(): Collection
    {
        return $this->model->newQuery()
            ->get(['group', 'key', 'value', 'is_encrypted'])
            ->keyBy('key');
    }

    /**
     * Insert or update a setting by its key.
     *
     * @param  array<string, mixed>  $data
     */
    public function upsert(string $key, array $data): Pengaturan
    {
        return $this->model->newQuery()->updateOrCreate(['key' => $key], $data);
    }
}
