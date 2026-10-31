<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\PerusahaanBuyerRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\LoginRateLimiter;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;

class AuthService
{
    /**
     * Create a new AuthService instance.
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly LoginRateLimiter $limiter,
        private readonly OtpService $otpService,
        private readonly PerusahaanBuyerRepositoryInterface $perusahaanBuyerRepository
    ) {}

    /**
     * Authenticate user with email and password.
     *
     * Inactive accounts that were never activated (new employees) must pass an
     * email OTP first; the result then carries `otp_required` instead of a login.
     *
     * @return array{success: bool, code: int, message: string, user?: User, otp_required?: bool, errors?: array<string, string>}
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

        if (! $user->status_aktif && $user->email_verified_at !== null) {
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

        if (! $user->status_aktif) {
            $this->limiter->clear($request);

            return $this->startLoginOtp($request, $user, $remember);
        }

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
     * Describe the login OTP step waiting in this session, if any.
     *
     * @return array{email: string, cooldown: int}|null
     */
    public function pendingLoginOtp(Request $request): ?array
    {
        $user = $this->pendingLoginUser($request);

        if (! $user) {
            return null;
        }

        return [
            'email' => $this->maskEmail($user->email),
            'cooldown' => $this->otpService->cooldownRemaining(OtpService::PURPOSE_LOGIN, $user->email),
        ];
    }

    /**
     * Verify the login OTP, activate the account, and sign the user in.
     *
     * @return array{success: bool, code: int, message: string, user: User}
     *
     * @throws ValidationException
     */
    public function verifyLoginOtp(Request $request, string $code): array
    {
        $user = $this->pendingLoginUser($request) ?? throw ValidationException::withMessages([
            'otp' => 'Sesi verifikasi berakhir. Silakan masuk kembali.',
        ]);

        $this->otpService->verify(OtpService::PURPOSE_LOGIN, $user->email, $code);

        $this->userRepository->update($user, [
            'status_aktif' => true,
            'email_verified_at' => now(),
        ]);

        $remember = (bool) $request->session()->get('login_otp.remember', false);
        $request->session()->forget('login_otp');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return [
            'success' => true,
            'code' => 200,
            'message' => 'Akun berhasil diaktivasi. Selamat datang di PAGORA.',
            'user' => $user,
        ];
    }

    /**
     * Send a fresh login OTP for the pending session.
     *
     * @throws ValidationException
     */
    public function resendLoginOtp(Request $request): void
    {
        $user = $this->pendingLoginUser($request) ?? throw ValidationException::withMessages([
            'otp' => 'Sesi verifikasi berakhir. Silakan masuk kembali.',
        ]);

        $this->otpService->send(OtpService::PURPOSE_LOGIN, $user->email, 'otp');
    }

    /**
     * Drop the pending login OTP step.
     */
    public function cancelLoginOtp(Request $request): void
    {
        $request->session()->forget('login_otp');
    }

    /**
     * Park the verified-password user in the session and email the OTP.
     *
     * @return array{success: bool, code: int, message: string, otp_required: bool}
     */
    private function startLoginOtp(LoginRequest $request, User $user, bool $remember): array
    {
        $this->otpService->send(OtpService::PURPOSE_LOGIN, $user->email, 'email');

        $request->session()->put('login_otp', [
            'uuid' => $user->uuid,
            'remember' => $remember,
        ]);

        return [
            'success' => true,
            'code' => 202,
            'message' => 'Kode OTP telah dikirim ke email Anda.',
            'otp_required' => true,
        ];
    }

    private function pendingLoginUser(Request $request): ?User
    {
        $uuid = $request->session()->get('login_otp.uuid');

        if (! is_string($uuid)) {
            return null;
        }

        $user = $this->userRepository->findByUuid($uuid);

        return $user && ! $user->status_aktif && $user->email_verified_at === null ? $user : null;
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return substr($local, 0, 2).str_repeat('*', max(1, strlen($local) - 2)).'@'.$domain;
    }

    /**
     * Email a password reset link. Only verified, active accounts qualify.
     *
     * @throws ValidationException
     */
    public function sendPasswordResetLink(string $email): string
    {
        $this->ensureCanResetPassword($email);

        $status = Password::sendResetLink(['email' => $email]);

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => 'Permintaan terlalu sering. Silakan tunggu sebentar sebelum meminta tautan baru.',
            ]);
        }

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => 'Tautan atur ulang kata sandi gagal dikirim. Silakan coba kembali.',
            ]);
        }

        return 'Tautan atur ulang kata sandi telah dikirim ke email Anda.';
    }

    /**
     * Reset the password with a valid token.
     *
     * @param  array{token: string, email: string, password: string, password_confirmation: string}  $data
     *
     * @throws ValidationException
     */
    public function resetPassword(array $data): string
    {
        $this->ensureCanResetPassword($data['email']);

        $status = Password::reset($data, function (User $user, string $password): void {
            // Password is hashed by the model cast.
            $this->userRepository->update($user, [
                'password' => $password,
                'remember_token' => Str::random(60),
            ]);

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'Tautan atur ulang kata sandi tidak valid atau sudah kedaluwarsa. Silakan minta tautan baru.',
            ]);
        }

        return 'Kata sandi berhasil diperbarui. Silakan masuk dengan kata sandi baru.';
    }

    /**
     * @throws ValidationException
     */
    private function ensureCanResetPassword(string $email): void
    {
        $user = $this->userRepository->findByEmail($email);

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'Alamat email belum terdaftar dalam sistem.',
            ]);
        }

        if ($user->email_verified_at === null) {
            throw ValidationException::withMessages([
                'email' => 'Akun Anda belum terverifikasi. Masuk dengan email dan kata sandi untuk verifikasi OTP terlebih dahulu.',
            ]);
        }

        if (! $user->status_aktif) {
            throw ValidationException::withMessages([
                'email' => 'Akun Anda dinonaktifkan. Silakan hubungi administrator.',
            ]);
        }
    }

    /**
     * Register a new tenant company and its administrator account.
     *
     * @return array{success: bool, code: int, message: string, user: User}
     */
    public function register(RegisterRequest $request): array
    {
        $name = (string) ($request->input('name') ?? $request->input('nama_lengkap'));
        $email = strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');

        $namaPerusahaan = trim((string) $request->input('nama_perusahaan'));
        $nomorNpwp = $request->filled('nomor_npwp') ? trim((string) $request->input('nomor_npwp')) : null;
        $alamat = $request->filled('alamat') ? trim((string) $request->input('alamat')) : null;

        $file = $request->file('dokumen_npwp');
        $documentPath = null;

        if ($file instanceof UploadedFile) {
            $extension = strtolower($file->guessExtension() ?: 'bin');
            $filename = Str::uuid()->toString().'.'.$extension;

            $documentPath = $file->storeAs(
                'perusahaan/npwp',
                $filename,
                'local'
            );

            if ($documentPath === false) {
                throw new RuntimeException('Dokumen NPWP gagal disimpan.');
            }
        }

        $user = DB::transaction(function () use ($name, $email, $password, $namaPerusahaan, $nomorNpwp, $alamat, $documentPath): User {
            $perusahaan = $this->perusahaanBuyerRepository->create([
                'nama_perusahaan' => $namaPerusahaan,
                'nomor_npwp' => $nomorNpwp,
                'file_url' => $documentPath,
                'alamat' => $alamat,
                'status_aktif' => true,
            ]);

            $ownerRole = Role::where('nama_role', 'owner')->first()
                ?? Role::where('nama_role', 'buyer')->first();
            $roleId = $ownerRole ? (int) $ownerRole->getKey() : 6;

            return $this->userRepository->create([
                'nama_lengkap' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role_id' => $roleId,
                'perusahaan_id' => (int) $perusahaan->getKey(),
                'status_aktif' => true,
            ]);
        });

        Auth::login($user);
        $request->session()->regenerate();

        return [
            'success' => true,
            'code' => 201,
            'message' => 'Pendaftaran akun tenant berhasil.',
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
            $needsActivation = $user->email_verified_at === null;

            return [
                'success' => false,
                'code' => 403,
                'message' => $needsActivation ? 'Akun belum diaktivasi.' : 'Akun dinonaktifkan.',
                'errors' => [
                    'google' => $needsActivation
                        ? 'Akun Anda belum diaktivasi. Masuk dengan email dan kata sandi untuk verifikasi OTP.'
                        : 'Akun Anda dinonaktifkan. Silakan hubungi administrator.',
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
