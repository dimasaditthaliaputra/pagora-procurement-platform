<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string $document_type
 * @property string|null $document_number
 * @property string $file_path
 * @property VerificationStatus $verification_status
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property string|null $rejection_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read PerusahaanBuyer|null $tenant
 * @property-read User|null $verifier
 */
class DokumenLegalitasTenant extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'tenant_legal_documents';

    /**
     * Attributes that can be mass assigned.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'tenant_id',
        'document_type',
        'document_number',
        'file_path',
        'verification_status',
        'verified_by',
        'verified_at',
        'rejection_reason',
    ];

    /**
     * Hide internal ID and private file path from serialized data.
     *
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'file_path',
    ];

    /**
     * Use UUID as the public identifier.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Cast attributes to their appropriate types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(PerusahaanBuyer::class, 'tenant_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
