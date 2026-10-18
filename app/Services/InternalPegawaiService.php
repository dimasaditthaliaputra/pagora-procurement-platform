<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Notifications\AkunPegawaiDibuatNotification;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class InternalPegawaiService
{
    /**
     * Default initial password assigned to newly created employee accounts.
     */
    public const DEFAULT_PASSWORD = 'Pagora@2026';

    /**
     * Allowed internal roles for employee accounts.
     *
     * @var array<string, string>
     */
    public const INTERNAL_ROLES = [
        'buyer' => 'Buyer (Tim Purchasing)',
        'qc_gudang' => 'QC Gudang (Inspeksi Mutu)',
    ];

    public function __construct(
        protected UserRepositoryInterface $userRepository
    ) {}

    /**
     * Paginate internal employees.
     *
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $columns
     */
    public function paginate(
        array $filters,
        int $perPage = 10,
        array $columns = [
            'id',
            'uuid',
            'nama_lengkap',
            'email',
            'avatar',
            'role_id',
            'status_aktif',
            'created_at',
            'updated_at',
        ]
    ): LengthAwarePaginator {
        return $this->userRepository->paginateInternal($filters, $perPage, $columns);
    }

    /**
     * Find an employee by UUID or throw ModelNotFoundException.
     *
     * @throws ModelNotFoundException
     */
    public function findByUuid(string $uuid): User
    {
        $user = $this->userRepository->findByUuid($uuid);

        if (! $user) {
            throw (new ModelNotFoundException('Akun pegawai tidak ditemukan.'))->setModel(User::class, [$uuid]);
        }

        return $user;
    }

    /**
     * Create a new internal employee account with default credentials and send email notification.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(array $data): User
    {
        $roleName = (string) ($data['role'] ?? '');
        $role = Role::where('nama_role', $roleName)->first();

        if (! $role || ! array_key_exists($roleName, self::INTERNAL_ROLES)) {
            throw ValidationException::withMessages([
                'role' => 'Role pegawai tidak valid atau tidak diizinkan.',
            ]);
        }

        return DB::transaction(function () use ($data, $role, $roleName): User {
            $defaultPassword = self::DEFAULT_PASSWORD;

            $user = $this->userRepository->create([
                'uuid' => (string) Str::uuid(),
                'nama_lengkap' => trim((string) $data['nama_lengkap']),
                'email' => strtolower(trim((string) $data['email'])),
                'password' => Hash::make($defaultPassword),
                'role_id' => $role->id,
                'status_aktif' => true,
            ]);

            $roleLabel = self::INTERNAL_ROLES[$roleName] ?? ucfirst($roleName);

            // Send notification email with initial access credentials
            try {
                $user->notify(new AkunPegawaiDibuatNotification($user, $defaultPassword, $roleLabel));
            } catch (Throwable $e) {
                Log::warning('Gagal mengirimkan email notifikasi akun pegawai baru: '.$e->getMessage(), [
                    'user_uuid' => $user->uuid,
                    'email' => $user->email,
                ]);
            }

            return $user->load('role');
        });
    }

    /**
     * Update an internal employee account.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function update(string $uuid, array $data): User
    {
        $user = $this->findByUuid($uuid);

        $roleName = (string) ($data['role'] ?? '');
        $role = Role::where('nama_role', $roleName)->first();

        if (! $role) {
            throw ValidationException::withMessages([
                'role' => 'Role yang dipilih tidak ditemukan dalam sistem.',
            ]);
        }

        return DB::transaction(function () use ($user, $data, $role): User {
            $payload = [
                'nama_lengkap' => trim((string) $data['nama_lengkap']),
                'email' => strtolower(trim((string) $data['email'])),
                'role_id' => $role->id,
                'status_aktif' => (bool) ($data['status_aktif'] ?? true),
            ];

            // If a new password is provided, hash and update it
            if (! empty($data['password'])) {
                $payload['password'] = Hash::make((string) $data['password']);
            }

            $this->userRepository->update($user, $payload);

            return $user->fresh()->load('role');
        });
    }

    /**
     * Soft delete an internal employee account.
     *
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function delete(string $uuid, ?User $currentUser = null): bool
    {
        $user = $this->findByUuid($uuid);

        if ($currentUser !== null && $user->id === $currentUser->id) {
            throw ValidationException::withMessages([
                'error' => 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.',
            ]);
        }

        return $this->userRepository->delete($user);
    }

    /**
     * Get available role options formatted for frontend Combobox / Select.
     *
     * @return list<array{value: string, label: string}>
     */
    public function getRoleOptions(): array
    {
        $options = [];
        foreach (self::INTERNAL_ROLES as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $options;
    }
}
