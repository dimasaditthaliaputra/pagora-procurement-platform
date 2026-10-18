<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    /**
     * Find a user by their ID.
     */
    public function findById(int|string $id): ?User;

    /**
     * Find a user by their email address.
     */
    public function findByEmail(string $email): ?User;

    /**
     * Find a user by their Google ID.
     */
    public function findByGoogleId(string $googleId): ?User;

    /**
     * Create a new user record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User;

    /**
     * Update Google ID and optional avatar for a user.
     */
    public function updateGoogleId(int|string $userId, string $googleId, ?string $avatar = null): bool;

    /**
     * Find a user by their UUID.
     */
    public function findByUuid(string $uuid): ?User;

    /**
     * Paginate internal employees with search, role filters, and sorting.
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
            'status_aktif',
            'created_at',
            'updated_at',
        ]
    ): LengthAwarePaginator;

    /**
     * Check if an email already exists, optionally ignoring a specific UUID.
     */
    public function emailExists(string $email, ?string $ignoreUuid = null): bool;

    /**
     * Soft delete a user.
     */
    public function delete(User $user): bool;

    /**
     * Update user attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): bool;
}
