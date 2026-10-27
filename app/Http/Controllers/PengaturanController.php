<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Pengaturan\UpdateIntegrasiRequest;
use App\Http\Requests\Pengaturan\UpdateProfilAplikasiRequest;
use App\Services\PengaturanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PengaturanController extends Controller
{
    public function __construct(
        protected PengaturanService $service
    ) {}

    /**
     * Show the application profile settings.
     */
    public function profil(): Response
    {
        return Inertia::render('Pengaturan/Profil', [
            'pengaturan' => $this->service->grupUntukForm(PengaturanService::GRUP_PROFIL),
        ]);
    }

    /**
     * Update the application profile settings.
     */
    public function updateProfil(UpdateProfilAplikasiRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $this->service->updateProfil(
                $request->safe()->except(['logo', 'hapus_logo']),
                $request->file('logo'),
                $request->boolean('hapus_logo'),
                $request->user()?->id
            );

            return ResponseHelper::success(
                'Profil aplikasi berhasil diperbarui.',
                $this->service->grupUntukForm(PengaturanService::GRUP_PROFIL),
                route('pengaturan.profil')
            );
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memperbarui profil aplikasi.', $e);
        }
    }

    /**
     * Show the API integration settings.
     */
    public function integrasi(): Response
    {
        return Inertia::render('Pengaturan/Integrasi', [
            'pengaturan' => $this->service->grupUntukForm(PengaturanService::GRUP_INTEGRASI),
            'provider_options' => PengaturanService::PROVIDER_OPTIONS,
            'mode_options' => PengaturanService::MODE_OPTIONS,
            'jadwal_sinkron_options' => PengaturanService::JADWAL_SINKRON_OPTIONS,
        ]);
    }

    /**
     * Update the API integration settings.
     */
    public function updateIntegrasi(UpdateIntegrasiRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $this->service->updateIntegrasi($request->validated(), $request->user()?->id);

            return ResponseHelper::success(
                'Pengaturan integrasi berhasil diperbarui.',
                $this->service->grupUntukForm(PengaturanService::GRUP_INTEGRASI),
                route('pengaturan.integrasi')
            );
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memperbarui pengaturan integrasi.', $e);
        }
    }
}
