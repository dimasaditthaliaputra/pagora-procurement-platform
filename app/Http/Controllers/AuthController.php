<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SendTenantOtpRequest;
use App\Http\Requests\Auth\VerifyLoginOtpRequest;
use App\Http\Requests\Auth\VerifyTenantOtpRequest;
use App\Services\AuthService;
use App\Services\PengaturanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     */
    public function __construct(
        private readonly AuthService $authService
    ) {}

    /**
     * Render the login page.
     */
    public function login(Request $request): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
            'otp' => $this->authService->pendingLoginOtp($request),
        ]);
    }

    /**
     * Process an email authentication attempt.
     */
    public function storeAuth(LoginRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $result = $this->authService->login($request);

            if ($result['success'] && ($result['otp_required'] ?? false)) {
                return ResponseHelper::success(
                    $result['message'],
                    ['otp_required' => true],
                    route('login'),
                    $request
                );
            }

            if ($result['success']) {
                return ResponseHelper::success(
                    $result['message'],
                    $result['user'] ?? null,
                    config('fortify.home', '/dashboard'),
                    $request
                );
            }

            if (($result['code'] ?? $result['status'] ?? null) === 403) {
                return ResponseHelper::forbidden(
                    $result['message'],
                    $result['errors'] ?? null,
                    $request
                );
            }

            return ResponseHelper::unauthorized(
                $result['message'],
                $result['errors'] ?? null,
                $request
            );
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors(), request: $request);
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Terjadi kesalahan saat memproses login.', $e, $request);
        }
    }

    /**
     * Verify the activation OTP and complete the login.
     */
    public function verifyLoginOtp(VerifyLoginOtpRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $result = $this->authService->verifyLoginOtp($request, (string) $request->validated('otp'));

            return ResponseHelper::success(
                $result['message'],
                $result['user'],
                config('fortify.home', '/dashboard'),
                $request
            );
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors(), request: $request);
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Terjadi kesalahan saat memverifikasi OTP.', $e, $request);
        }
    }

    /**
     * Send a new activation OTP.
     */
    public function resendLoginOtp(Request $request): JsonResponse|RedirectResponse
    {
        try {
            $this->authService->resendLoginOtp($request);

            return ResponseHelper::success('Kode OTP baru telah dikirim ke email Anda.', null, route('login'), $request);
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors(), request: $request);
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Terjadi kesalahan saat mengirim OTP.', $e, $request);
        }
    }

    /**
     * Abandon the OTP step and return to the login form.
     */
    public function cancelLoginOtp(Request $request): JsonResponse|RedirectResponse
    {
        $this->authService->cancelLoginOtp($request);

        return ResponseHelper::success('Verifikasi dibatalkan.', null, route('login'), $request);
    }

    /**
     * Render the forgot password page.
     */
    public function forgotPassword(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    /**
     * Email a password reset link to a verified account.
     */
    public function storeForgotPassword(ForgotPasswordRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $message = $this->authService->sendPasswordResetLink((string) $request->validated('email'));

            return ResponseHelper::success($message, null, route('password.request'), $request);
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors(), request: $request);
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Terjadi kesalahan saat mengirim tautan atur ulang kata sandi.', $e, $request);
        }
    }

    /**
     * Render the reset password page from the emailed link.
     */
    public function resetPassword(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    /**
     * Save the new password using the reset token.
     */
    public function storeResetPassword(ResetPasswordRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $message = $this->authService->resetPassword(
                $request->only('token', 'email', 'password', 'password_confirmation')
            );

            return ResponseHelper::success($message, null, route('login'), $request);
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors(), request: $request);
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Terjadi kesalahan saat mengatur ulang kata sandi.', $e, $request);
        }
    }

    /**
     * Render the registration page.
     */
    public function register(PengaturanService $pengaturanService): Response
    {
        return Inertia::render('Auth/Register', [
            'google_user' => session('google_user'),
            'status' => session('status'),
            'acceptance' => $pengaturanService->termsAcceptance(),
        ]);
    }

    /**
     * Send an OTP code to the tenant registration email.
     */
    public function sendRegisterOtp(SendTenantOtpRequest $request): JsonResponse
    {
        try {
            $this->authService->sendRegisterOtp((string) $request->validated('email'));

            return response()->json([
                'success' => true,
                'message' => 'Kode OTP telah dikirim ke email bisnis Anda.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan validasi data.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim kode OTP.',
            ], 500);
        }
    }

    /**
     * Verify the tenant registration email OTP code before proceeding.
     */
    public function verifyRegisterOtp(VerifyTenantOtpRequest $request): JsonResponse
    {
        try {
            $this->authService->verifyRegisterOtp(
                (string) $request->validated('email'),
                (string) $request->validated('otp')
            );

            return response()->json([
                'success' => true,
                'message' => 'Kode OTP berhasil diverifikasi.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan validasi data.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memverifikasi kode OTP.',
            ], 500);
        }
    }

    /**
     * Process a user registration.
     */
    public function storeRegister(RegisterRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $result = $this->authService->register($request);

            session()->flash('registered_email', (string) $request->validated('email'));

            if ($result['success']) {
                return ResponseHelper::success(
                    $result['message'],
                    $result['user'] ?? null,
                    route('register.success'),
                    $request
                );
            }

            return ResponseHelper::badRequest(
                $result['message'],
                $result['errors'] ?? null,
                $request
            );
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors(), 'Terjadi kesalahan validasi data.', $request);
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Terjadi kesalahan saat memproses pendaftaran.', $e, $request);
        }
    }

    /**
     * Redirect to Google OAuth provider.
     */
    public function redirectToGoogle(Request $request): SymfonyResponse
    {
        $flow = (string) $request->query('flow', 'tenant');
        session(['google_auth_flow' => $flow]);

        return $this->authService->getGoogleRedirect();
    }

    /**
     * Handle the callback from Google OAuth.
     */
    public function handleGoogleCallback(Request $request): JsonResponse|RedirectResponse|SymfonyResponse
    {
        try {
            $result = $this->authService->handleGoogleCallback();

            if ($result['success']) {
                if (! empty($result['is_new_registration'])) {
                    $flow = $result['registration_flow'] ?? 'tenant';
                    $targetRoute = $flow === 'vendor' ? 'register.vendor' : 'register';

                    return redirect()->route($targetRoute)->with(
                        'status',
                        'Akun Google ('.($result['google_user']['email'] ?? '').') berhasil terhubung. Silakan lengkapi data perusahaan Anda.'
                    );
                }

                return ResponseHelper::success(
                    $result['message'],
                    $result['user'] ?? null,
                    config('fortify.home', '/dashboard'),
                    $request
                );
            }

            if (($result['code'] ?? $result['status'] ?? null) === 403) {
                if ($request->expectsJson()) {
                    return ResponseHelper::forbidden(
                        $result['message'],
                        $result['errors'] ?? null,
                        $request
                    );
                }

                return response($result['errors']['google'] ?? $result['message'], 403);
            }

            if ($request->expectsJson()) {
                return ResponseHelper::unauthorized(
                    $result['message'],
                    $result['errors'] ?? null,
                    $request
                );
            }

            return redirect()->route('login')->withErrors($result['errors'] ?? ['google' => $result['message']]);
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Terjadi kesalahan saat memproses autentikasi Google.', $e, $request);
        }
    }

    /**
     * Cancel Google authentication session and return to registration.
     */
    public function cancelGoogleAuth(Request $request): RedirectResponse
    {
        $flow = session('google_auth_flow', 'tenant');
        $request->session()->forget(['google_user', 'google_auth_flow']);

        $targetRoute = $flow === 'vendor' ? 'register.vendor' : 'register';

        return redirect()->route($targetRoute)->with('status', 'Pendaftaran akun Google dibatalkan. Anda dapat mendaftar secara manual.');
    }

    /**
     * Log out the authenticated user.
     */
    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        try {
            $result = $this->authService->logout($request);

            return ResponseHelper::success(
                $result['message'],
                null,
                route('login'),
                $request
            );
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Terjadi kesalahan saat memproses logout.', $e, $request);
        }
    }
}
