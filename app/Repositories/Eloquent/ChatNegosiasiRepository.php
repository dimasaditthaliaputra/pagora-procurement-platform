<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ChatNegosiasi;
use App\Repositories\Contracts\ChatNegosiasiRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ChatNegosiasiRepository implements ChatNegosiasiRepositoryInterface
{
    /** Relations read by ChatNegosiasiResource. */
    private const DETAILS = ['pengirim:id,uuid,name,role_id,tenant_id,vendor_id', 'pengirim.role:id,name', 'item:id,uuid,komoditas_id', 'item.komoditas:id,name,unit'];

    public function __construct(
        protected ChatNegosiasi $model
    ) {}

    public function create(array $attributes): ChatNegosiasi
    {
        return $this->model->newQuery()->create(['uuid' => (string) Str::uuid(), ...$attributes]);
    }

    public function withDetails(ChatNegosiasi $message): ChatNegosiasi
    {
        return $message->load(self::DETAILS);
    }

    public function forPurchaseOrder(int $purchaseOrderId, ?string $afterUuid = null, int $limit = 200): Collection
    {
        $afterId = $afterUuid !== null
            ? $this->model->newQuery()->where('purchase_order_id', $purchaseOrderId)->where('uuid', $afterUuid)->value('id')
            : null;

        $query = $this->model->newQuery()
            ->where('purchase_order_id', $purchaseOrderId)
            ->with(self::DETAILS);

        if ($afterId !== null) {
            return $query->where('id', '>', $afterId)->orderBy('id')->limit($limit)->get();
        }

        // Newest `$limit`, returned oldest first.
        return $query->orderByDesc('id')->limit($limit)->get()->reverse()->values();
    }
}
