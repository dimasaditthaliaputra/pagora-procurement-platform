<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\UploadedFile;

class ProfilService
{
    private const AVATAR_DISK = 'public';

    private const AVATAR_DIRECTORY = 'avatar';

    public function __construct(
        protected UserRepositoryInterface $repository,
        protected FileStorageService $fileStorage
    ) {}

    /**
     * Update the signed-in user's own profile, including optional avatar upload or removal.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProfil(User $user, array $data, ?UploadedFile $avatar, bool $hapusAvatar): User
    {
        $avatarLama = $user->avatar;
        $avatarBaru = $avatar ? $this->fileStorage->store($avatar, self::AVATAR_DIRECTORY, self::AVATAR_DISK) : null;

        $payload = ['name' => $data['name']];

        if (is_string($avatarBaru)) {
            $payload['avatar'] = $avatarBaru;
        } elseif ($hapusAvatar) {
            $payload['avatar'] = null;
        }

        $this->fileStorage->rollbackOnFailure(
            self::AVATAR_DISK,
            $avatarBaru,
            fn () => $this->repository->update($user, $payload)
        );

        // Google sign-in stores an external URL; FileStorageService::delete() skips it.
        if (array_key_exists('avatar', $payload)) {
            $this->fileStorage->delete(self::AVATAR_DISK, $avatarLama);
        }

        return $user->fresh();
    }

    /**
     * Change the signed-in user's password. The current password is verified by the Form Request.
     */
    public function updatePassword(User $user, string $password): void
    {
        // The model's `hashed` cast hashes the plain value on save.
        $this->repository->update($user, ['password' => $password]);
    }
}
