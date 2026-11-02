<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyLoginOtpRequest;
use App\Services\AuthService;
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
    public function register(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Process a user registration.
     */
    public function storeRegister(RegisterRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $result = $this->authService->register($request);

            if ($result['success']) {
                return ResponseHelper::created(
                    $result['message'],
                    $result['user'] ?? null,
                    config('fortify.home', '/dashboard'),
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
    public function redirectToGoogle(): SymfonyResponse
    {
        return $this->authService->getGoogleRedirect();
    }

    /**
     * Handle the callback from Google OAuth.
     */
    public function handleGoogleCallback(Request $request): JsonResponse|RedirectResponse
    {
        try {
            $result = $this->authService->handleGoogleCallback();

            if ($result['success']) {
                return ResponseHelper::success(
                    $result['message'],
                    $result['user'] ?? null,
                    config('fortify.home', '/dashboard'),
                    $request
                );
            }

            return ResponseHelper::unauthorized(
                $result['message'],
                $result['errors'] ?? null,
                $request
            );
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Terjadi kesalahan saat memproses autentikasi Google.', $e, $request);
        }
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
