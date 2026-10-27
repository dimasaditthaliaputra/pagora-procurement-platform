<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Profil\UpdatePasswordRequest;
use App\Http\Requests\Profil\UpdateProfilRequest;
use App\Http\Resources\ProfilResource;
use App\Services\ProfilService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProfilController extends Controller
{
    public function __construct(
        protected ProfilService $service
    ) {}

    /**
     * Show the signed-in user's profile.
     */
    public function index(Request $request): Response
    {
        $user = $request->user()->loadMissing(['role', 'vendor', 'perusahaanBuyer']);

        return Inertia::render('Profil/Index', [
            'profil' => new ProfilResource($user),
        ]);
    }

    /**
     * Update the signed-in user's profile.
     */
    public function update(UpdateProfilRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $user = $this->service->updateProfil(
                $request->user(),
                $request->safe()->only(['nama_lengkap']),
                $request->file('avatar'),
                $request->boolean('hapus_avatar')
            );

            return ResponseHelper::success(
                'Profil berhasil diperbarui.',
                new ProfilResource($user->loadMissing(['role', 'vendor', 'perusahaanBuyer'])),
                route('profil.index')
            );
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memperbarui profil.', $e);
        }
    }

    /**
     * Change the signed-in user's password.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $this->service->updatePassword($request->user(), (string) $request->validated('password'));

            return ResponseHelper::success(
                'Kata sandi berhasil diperbarui.',
                null,
                route('profil.index')
            );
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memperbarui kata sandi.', $e);
        }
    }
}
