<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function login(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]);
    }

    /**
     * Process an email authentication attempt.
     */
    public function storeAuth(LoginRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $result = $this->authService->login($request);

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
            return ResponseHelper::serverError('Terjadi kesalahan saat memproses login.', $e, $request);
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
