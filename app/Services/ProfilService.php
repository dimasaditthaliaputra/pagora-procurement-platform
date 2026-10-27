<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfilService
{
    private const AVATAR_DISK = 'public';

    private const AVATAR_DIRECTORY = 'avatar';

    public function __construct(
        protected UserRepositoryInterface $repository
    ) {}

    /**
     * Update the signed-in user's own profile, including optional avatar upload or removal.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProfil(User $user, array $data, ?UploadedFile $avatar, bool $hapusAvatar): User
    {
        $avatarLama = $user->avatar;
        $avatarBaru = $avatar?->store(self::AVATAR_DIRECTORY, self::AVATAR_DISK);

        $payload = ['nama_lengkap' => $data['nama_lengkap']];

        if (is_string($avatarBaru)) {
            $payload['avatar'] = $avatarBaru;
        } elseif ($hapusAvatar) {
            $payload['avatar'] = null;
        }

        try {
            $this->repository->update($user, $payload);
        } catch (\Throwable $e) {
            if (is_string($avatarBaru)) {
                Storage::disk(self::AVATAR_DISK)->delete($avatarBaru);
            }

            throw $e;
        }

        if (array_key_exists('avatar', $payload) && $this->isStoredAvatar($avatarLama)) {
            Storage::disk(self::AVATAR_DISK)->delete($avatarLama);
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

    /**
     * Google sign-in stores an external URL that must never be passed to Storage::delete().
     */
    private function isStoredAvatar(?string $avatar): bool
    {
        return filled($avatar) && ! Str::startsWith($avatar, ['http://', 'https://']);
    }
}
