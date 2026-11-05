<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusPengiriman;
use Database\Factories\PengirimanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Self-reporting logistics model (`shipments` table — UC-14, FR-22, FR-23).
 *
 * @property int $id
 * @property string $uuid
 * @property int $purchase_order_id
 * @property int|null $dispute_id
 * @property string $route
 * @property string $carrier_name
 * @property string $vehicle_plate_number
 * @property Carbon $estimated_arrival_date
 * @property StatusPengiriman $status
 * @property int $reported_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PurchaseOrder|null $purchaseOrder
 * @property-read Sengketa|null $dispute
 * @property-read User|null $reporter
 * @property-read User|null $pelapor
 */
#[Fillable([
    'uuid',
    'purchase_order_id',
    'dispute_id',
    'route',
    'carrier_name',
    'vehicle_plate_number',
    'estimated_arrival_date',
    'status',
    'reported_by',
])]
#[Hidden(['id', 'purchase_order_id', 'dispute_id', 'reported_by'])]
class Pengiriman extends Model
{
    /** @use HasFactory<PengirimanFactory> */
    use HasFactory, HasUuids;

    protected $table = 'shipments';

    /**
     * Columns that store UUIDs.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Get route key name for model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusPengiriman::class,
            'estimated_arrival_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Purchase Order associated with this shipment.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    /**
     * Optional dispute that triggered a reshipment (UC-17, BR-09).
     */
    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Sengketa::class, 'dispute_id');
    }

    /**
     * Vendor user who reported this shipment.
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * Alias for reporter() preserving domain consistency.
     */
    public function pelapor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
