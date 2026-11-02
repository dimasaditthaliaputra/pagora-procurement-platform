<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Perusahaan\IndexPerusahaanRequest;
use App\Http\Requests\Perusahaan\UpdatePerusahaanRequest;
use App\Models\User;
use App\Services\PerusahaanService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PerusahaanController extends Controller
{
    public function __construct(
        protected readonly PerusahaanService $service
    ) {}

    /**
     * Display the company profile for the authenticated Owner or Vendor.
     */
    public function index(IndexPerusahaanRequest $request): Response|JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $payload = $this->service->getPerusahaanForUser($user);

            if ($request->expectsJson()) {
                return ResponseHelper::success('Profil perusahaan berhasil dimuat.', $payload);
            }

            return Inertia::render('Manajemen/Perusahaan/Index', [
                'perusahaan' => $payload,
            ]);
        } catch (AuthorizationException $e) {
            return ResponseHelper::forbidden($e->getMessage(), null, $request);
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memuat profil perusahaan.', $e);
        }
    }

    /**
     * Update the company profile.
     */
    public function update(UpdatePerusahaanRequest $request): JsonResponse|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            return ResponseHelper::forbidden('Sesi pengguna tidak valid.', null, $request);
        }

        try {
            $updated = $this->service->updateForUser($user, $request->validated());

            return ResponseHelper::success(
                'Data profil perusahaan berhasil diperbarui.',
                $updated,
                route('manajemen.perusahaan.index')
            );
        } catch (AuthorizationException $e) {
            return ResponseHelper::forbidden($e->getMessage(), null, $request);
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memperbarui data perusahaan.', $e);
        }
    }
}
