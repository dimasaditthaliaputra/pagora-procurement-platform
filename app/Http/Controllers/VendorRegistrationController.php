<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Auth\SendVendorOtpRequest;
use App\Http\Requests\Auth\StoreVendorRegistrationRequest;
use App\Http\Requests\Auth\VerifyVendorOtpRequest;
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
        $commodityCategories = $this->service->getActiveCommodityCategories();
        $invitation = null;
        $invitationError = null;
        $token = $request->query('invitation');

        if (is_string($token) && $token !== '') {
            $undangan = $this->service->findValidInvitationByToken($token);

            if ($undangan !== null) {
                $matchedCategoryUuid = null;
                if (filled($undangan->kategori_komoditas)) {
                    $matchedCategory = collect($commodityCategories)
                        ->firstWhere('name', $undangan->kategori_komoditas);
                    $matchedCategoryUuid = $matchedCategory['uuid'] ?? null;
                }

                $invitation = [
                    'token' => $undangan->token,
                    'company_name' => $undangan->company_name,
                    'contact_email' => $undangan->email,
                    'kategori_komoditas' => $undangan->kategori_komoditas,
                    'commodity_category_uuid' => $matchedCategoryUuid,
                ];
            } else {
                $invitationError = 'Tautan undangan pendaftaran vendor tidak valid atau sudah kedaluwarsa.';
            }
        }

        return Inertia::render('Auth/RegisterVendor', [
            'commodity_categories' => $commodityCategories,
            'kategori_options' => VendorRegistrationService::KATEGORI_OPTIONS,
            'status' => session('status'),
            'invitation' => $invitation,
            'invitation_error' => $invitationError,
        ]);
    }

    /**
     * Kirim kode OTP ke email bisnis untuk memastikan email tersebut asli.
     */
    public function sendOtp(SendVendorOtpRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $this->service->sendEmailOtp((string) $request->validated('contact_email'));

            return ResponseHelper::success('Kode OTP telah dikirim ke email bisnis Anda.');
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal mengirim kode OTP.', $e);
        }
    }

    /**
     * Verifikasi kode OTP email bisnis calon vendor sebelum lanjut ke langkah berikutnya.
     */
    public function verifyOtp(VerifyVendorOtpRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $this->service->verifyEmailOtp(
                (string) $request->validated('contact_email'),
                (string) $request->validated('otp')
            );

            return ResponseHelper::success(
                'Kode OTP berhasil diverifikasi.',
                null,
                url()->previous() ?: route('register.vendor'),
                $request
            );
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors(), request: $request);
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memverifikasi kode OTP.', $e, $request);
        }
    }

    /**
     * Tampilkan halaman konfirmasi setelah pendaftaran berhasil dikirim.
     */
    public function success(Request $request): Response
    {
        return Inertia::render('Auth/RegisterSuccess', [
            'email' => session('registered_email'),
        ]);
    }

    /**
     * Proses penyimpanan registrasi calon vendor mitra baru.
     */
    public function store(StoreVendorRegistrationRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $this->service->register($request->validated());

            session()->flash('registered_email', (string) $request->validated('contact_email'));

            return ResponseHelper::success(
                'Pendaftaran sukses! Akun dan dokumen legalitas Anda sedang menunggu verifikasi.',
                redirectTo: route('register.success')
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
