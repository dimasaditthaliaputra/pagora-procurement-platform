<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Auth\SendVendorOtpRequest;
use App\Http\Requests\Auth\StoreVendorRegistrationRequest;
use App\Services\VendorRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class VendorRegistrationController extends Controller
{
    public function __construct(
        private readonly VendorRegistrationService $service
    ) {}

    /**
     * Tampilkan formulir pendaftaran calon vendor mitra.
     */
    public function create(Request $request): Response
    {
        $invitation = null;
        $token = $request->query('invitation');

        if (is_string($token) && $token !== '') {
            $undangan = $this->service->findValidInvitationByToken($token);
            if ($undangan !== null) {
                $invitation = [
                    'token' => $undangan->token,
                    'nama_perusahaan' => $undangan->nama_perusahaan,
                    'email_kontak' => $undangan->email,
                    'kategori_komoditas' => $undangan->kategori_komoditas,
                ];
            }
        }

        return Inertia::render('Auth/RegisterVendor', [
            'kategori_options' => VendorRegistrationService::KATEGORI_OPTIONS,
            'status' => session('status'),
            'invitation' => $invitation,
        ]);
    }

    /**
     * Kirim kode OTP ke email bisnis untuk memastikan email tersebut asli.
     */
    public function sendOtp(SendVendorOtpRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $this->service->sendEmailOtp((string) $request->validated('email_kontak'));

            return ResponseHelper::success('Kode OTP telah dikirim ke email bisnis Anda.');
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal mengirim kode OTP.', $e);
        }
    }

    /**
     * Proses penyimpanan registrasi calon vendor mitra baru.
     */
    public function store(StoreVendorRegistrationRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $this->service->register($request->validated());

            return ResponseHelper::success(
                'Pendaftaran berhasil disubmit dan sedang dalam tahap validasi Manajemen.',
                redirectTo: route('register.vendor')
            );
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors());
        } catch (Throwable $e) {
            return ResponseHelper::serverError(
                'Terjadi kesalahan saat memproses pendaftaran vendor.',
                $e
            );
        }
    }
}
