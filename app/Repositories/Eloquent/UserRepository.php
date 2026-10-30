<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserRepository implements UserRepositoryInterface
{
    /**
     * Whitelist of sortable columns for internal users.
     *
     * @var list<string>
     */
    private const SORTABLE = [
        'nama_lengkap',
        'email',
        'role_id',
        'status_aktif',
        'created_at',
    ];

    /**
     * Find a user by their ID.
     */
    public function findById(int|string $id): ?User
    {
        return User::find($id);
    }

    /**
     * Find a user by their UUID.
     */
    public function findByUuid(string $uuid): ?User
    {
        return User::with([
            'role',
            'perusahaanBuyer:id,uuid,nama_perusahaan',
            'vendor:id,uuid,nama_perusahaan',
        ])->where('uuid', $uuid)->first();
    }

    /**
     * Paginate users with search, role/company filters, sorting, and tenant scoping.
     *
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $columns
     */
    public function paginateInternal(
        array $filters,
        int $perPage = 10,
        array $columns = [
            'id',
            'uuid',
            'nama_lengkap',
            'email',
            'avatar',
            'role_id',
            'perusahaan_id',
            'vendor_id',
            'status_aktif',
            'created_at',
            'updated_at',
        ],
        ?int $vendorId = null,
        ?int $perusahaanId = null,
        bool $isSuperadmin = false,
        bool $unassignedOnly = false,
        bool $forceEmpty = false
    ): LengthAwarePaginator {
        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true)
            ? (string) $filters['sort']
            : 'created_at';

        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $driver = DB::getDriverName();
        $like = $driver === 'pgsql' ? 'ilike' : 'like';

        return User::query()
            ->select($columns)
            ->with([
                'role',
                'perusahaanBuyer:id,uuid,nama_perusahaan',
                'vendor:id,uuid,nama_perusahaan',
            ])
            ->when($forceEmpty, function ($query): void {
                $query->whereRaw('1 = 0');
            }, function ($query) use ($isSuperadmin, $vendorId, $perusahaanId, $unassignedOnly): void {
                if (! $isSuperadmin) {
                    if ($vendorId !== null) {
                        $query->where('vendor_id', $vendorId)
                            ->whereNull('perusahaan_id')
                            ->whereHas('role', function ($q): void {
                                $q->where('nama_role', '!=', 'superadmin');
                            });
                    } elseif ($perusahaanId !== null) {
                        $query->where('perusahaan_id', $perusahaanId)
                            ->whereNull('vendor_id')
                            ->whereHas('role', function ($q): void {
                                $q->where('nama_role', '!=', 'superadmin');
                            });
                    } else {
                        $query->whereRaw('1 = 0');
                    }

                    return;
                }

                // Superadmin scope with optional company filter
                if ($unassignedOnly) {
                    $query->whereNull('perusahaan_id')->whereNull('vendor_id');
                } elseif ($perusahaanId !== null) {
                    $query->where('perusahaan_id', $perusahaanId);
                } elseif ($vendorId !== null) {
                    $query->where('vendor_id', $vendorId);
                }
            })
            ->when(! empty($filters['search']), function ($query) use ($filters, $like): void {
                $term = '%'.trim((string) $filters['search']).'%';
                $query->where(function ($q) use ($term, $like): void {
                    $q->where('nama_lengkap', $like, $term)
                        ->orWhere('email', $like, $term);
                });
            })
            ->when(! empty($filters['role']) && $filters['role'] !== 'all', function ($query) use ($filters): void {
                $query->whereHas('role', function ($q) use ($filters): void {
                    $q->where('nama_role', $filters['role']);
                });
            })
            ->when(isset($filters['status_aktif']) && $filters['status_aktif'] !== '' && $filters['status_aktif'] !== 'all', function ($query) use ($filters): void {
                $status = filter_var($filters['status_aktif'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($status !== null) {
                    $query->where('status_aktif', $status);
                }
            })
            ->orderBy($sort, $direction)
            ->paginate(min(max($perPage, 1), 100))
            ->withQueryString();
    }

    /**
     * Check if an email already exists, optionally ignoring a specific UUID.
     */
    public function emailExists(string $email, ?string $ignoreUuid = null): bool
    {
        return User::where('email', $email)
            ->when($ignoreUuid, fn ($q) => $q->where('uuid', '!=', $ignoreUuid))
            ->exists();
    }

    /**
     * Soft delete a user.
     */
    public function delete(User $user): bool
    {
        return (bool) $user->delete();
    }

    /**
     * Find a user by their email address.
     */
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * Find a user by their Google ID.
     */
    public function findByGoogleId(string $googleId): ?User
    {
        return User::where('google_id', $googleId)->first();
    }

    /**
     * Create a new user record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        if (empty($data['uuid'])) {
            $data['uuid'] = (string) Str::uuid();
        }

        if (! isset($data['role_id'])) {
            $data['role_id'] = 2;
        }

        if (! isset($data['status_aktif'])) {
            $data['status_aktif'] = true;
        }

        return User::create($data);
    }

    /**
     * Update Google ID and optional avatar for a user.
     */
    public function updateGoogleId(int|string $userId, string $googleId, ?string $avatar = null): bool
    {
        $user = $this->findById($userId);

        if (! $user) {
            return false;
        }

        $payload = ['google_id' => $googleId];

        if ($avatar !== null && empty($user->avatar)) {
            $payload['avatar'] = $avatar;
        }

        return $user->update($payload);
    }

    /**
     * Update user attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): bool
    {
        return $user->update($data);
    }
}
