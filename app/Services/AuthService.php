<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\LoginRateLimiter;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;

class AuthService
{
    /**
     * Create a new AuthService instance.
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly LoginRateLimiter $limiter
    ) {}

    /**
     * Authenticate user with email and password.
     *
     * @return array{success: bool, code: int, message: string, user?: User, errors?: array<string, string>}
     */
    public function login(LoginRequest $request): array
    {
        if ($this->limiter->tooManyAttempts($request)) {
            $seconds = $this->limiter->availableIn($request);

            return [
                'success' => false,
                'code' => 422,
                'message' => 'Terlalu banyak percobaan masuk.',
                'errors' => [
                    'email' => "Terlalu banyak percobaan masuk. Silakan coba kembali dalam {$seconds} detik.",
                ],
            ];
        }

        $email = (string) $request->input('email');
        $user = $this->userRepository->findByEmail($email);

        if (! $user) {
            $this->limiter->increment($request);

            return [
                'success' => false,
                'code' => 422,
                'message' => 'Alamat email belum terdaftar dalam sistem.',
                'errors' => [
                    'email' => 'Alamat email belum terdaftar dalam sistem.',
                ],
            ];
        }

        if (! $user->status_aktif) {
            $this->limiter->increment($request);

            return [
                'success' => false,
                'code' => 403,
                'message' => 'Akun dinonaktifkan.',
                'errors' => [
                    'email' => 'Akun Anda dinonaktifkan. Silakan hubungi administrator.',
                ],
            ];
        }

        $password = (string) $request->input('password');
        if (! Hash::check($password, $user->password ?? '')) {
            $this->limiter->increment($request);

            return [
                'success' => false,
                'code' => 422,
                'message' => 'Kata sandi salah.',
                'errors' => [
                    'password' => 'Kata sandi yang Anda masukkan salah.',
                ],
            ];
        }

        $remember = $request->boolean('remember');
        Auth::login($user, $remember);
        $this->limiter->clear($request);
        $request->session()->regenerate();

        return [
            'success' => true,
            'code' => 200,
            'message' => 'Berhasil masuk ke sistem.',
            'user' => $user,
        ];
    }

    /**
     * Register a new user with email and password.
     *
     * @return array{success: bool, code: int, message: string, user: User}
     */
    public function register(RegisterRequest $request): array
    {
        $name = (string) ($request->input('name') ?? $request->input('nama_lengkap'));
        $email = (string) $request->input('email');
        $password = (string) $request->input('password');

        $user = $this->userRepository->create([
            'nama_lengkap' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role_id' => 2,
            'status_aktif' => true,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return [
            'success' => true,
            'code' => 201,
            'message' => 'Pendaftaran akun berhasil.',
            'user' => $user,
        ];
    }

    /**
     * Redirect the user to Google OAuth page.
     */
    public function getGoogleRedirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the callback from Google OAuth.
     *
     * @return array{success: bool, code: int, message: string, user?: User, errors?: array<string, string>}
     */
    public function handleGoogleCallback(): array
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::error('Autentikasi Google gagal saat proses OAuth', [
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'code' => 401,
                'message' => 'Gagal melakukan autentikasi akun Google.',
                'errors' => [
                    'google' => 'Gagal melakukan autentikasi akun Google. Silakan coba kembali.',
                ],
            ];
        }

        $googleId = (string) $googleUser->getId();
        $email = (string) $googleUser->getEmail();

        $user = $this->userRepository->findByGoogleId($googleId);

        if (! $user && ! empty($email)) {
            $user = $this->userRepository->findByEmail($email);
            if ($user) {
                $this->userRepository->updateGoogleId($user->id, $googleId, $googleUser->getAvatar());
                $user->refresh();
            }
        }

        if (! $user) {
            return [
                'success' => false,
                'code' => 401,
                'message' => 'Akun Google belum terdaftar.',
                'errors' => [
                    'google' => 'Akun Google Anda belum terdaftar dalam sistem. Silakan mendaftar terlebih dahulu.',
                ],
            ];
        }

        if (! $user->status_aktif) {
            return [
                'success' => false,
                'code' => 403,
                'message' => 'Akun dinonaktifkan.',
                'errors' => [
                    'google' => 'Akun Anda dinonaktifkan. Silakan hubungi administrator.',
                ],
            ];
        }

        Auth::login($user, true);
        request()->session()->regenerate();

        return [
            'success' => true,
            'code' => 200,
            'message' => 'Berhasil masuk dengan akun Google.',
            'user' => $user,
        ];
    }

    /**
     * Log the user out of the application and invalidate the session.
     *
     * @return array{success: bool, code: int, message: string}
     */
    public function logout(Request $request): array
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return [
            'success' => true,
            'code' => 200,
            'message' => 'Berhasil keluar.',
        ];
    }
}
