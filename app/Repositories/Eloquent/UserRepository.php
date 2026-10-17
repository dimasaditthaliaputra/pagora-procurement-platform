<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Str;

class UserRepository implements UserRepositoryInterface
{
    /**
     * Find a user by their ID.
     */
    public function findById(int|string $id): ?User
    {
        return User::find($id);
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
