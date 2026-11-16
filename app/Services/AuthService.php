<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\VerificationStatus;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use App\Notifications\GoogleRegistrationPasswordNotification;
use App\Repositories\Contracts\PerusahaanBuyerRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
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

        if (! $user->is_active && $user->email_verified_at !== null) {
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

        if ($companyBlock = $this->checkCompanyVerificationAndStatus($user, 'email')) {
            $this->limiter->increment($request);

            return $companyBlock;
        }

        $remember = $request->boolean('remember');

        if (! $user->is_active) {
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

        if ($companyBlock = $this->checkCompanyVerificationAndStatus($user, 'otp')) {
            throw ValidationException::withMessages($companyBlock['errors']);
        }

        $this->userRepository->update($user, [
            'is_active' => true,
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

        return $user && ! $user->is_active && $user->email_verified_at === null ? $user : null;
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

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Akun Anda dinonaktifkan. Silakan hubungi administrator.',
            ]);
        }
    }

    /**
     * Send a verification OTP to the tenant registration email.
     */
    public function sendRegisterOtp(string $email): void
    {
        $this->otpService->send(
            OtpService::PURPOSE_TENANT_REGISTRATION,
            $email,
            'email'
        );
    }

    /**
     * Verify the tenant registration email OTP without consuming it immediately.
     */
    public function verifyRegisterOtp(string $email, string $otp): void
    {
        $this->otpService->check(
            OtpService::PURPOSE_TENANT_REGISTRATION,
            $email,
            $otp
        );
    }

    /**
     * Register a new tenant company and its administrator account.
     *
     * @return array{success: bool, code: int, message: string, user: User}
     */
    public function register(RegisterRequest $request): array
    {
        $isGoogleRegistration = $request->boolean('is_google_registration')
            || session()->has('google_user')
            || filled($request->input('google_id'));

        $name = (string) ($request->input('name') ?? $request->input('nama_lengkap'));
        $email = strtolower(trim((string) $request->input('email')));
        $googleUser = session('google_user', []);
        $googleId = (string) ($request->input('google_id') ?? ($googleUser['google_id'] ?? null));
        $avatar = (string) ($request->input('avatar') ?? ($googleUser['avatar'] ?? null));

        $defaultPassword = null;
        if ($isGoogleRegistration) {
            $defaultPassword = (string) ($request->input('default_password') ?? ('Pagora#'.Str::random(10)));
            $password = $defaultPassword;
        } else {
            $password = (string) $request->input('password');
            $otp = (string) $request->input('otp');

            $this->otpService->verify(
                OtpService::PURPOSE_TENANT_REGISTRATION,
                $email,
                $otp
            );
        }

        $namaPerusahaan = trim((string) ($request->input('nama_perusahaan') ?? $request->input('company_name')));
        $nomorNpwp = $request->filled('nomor_npwp')
            ? trim((string) $request->input('nomor_npwp'))
            : ($request->filled('tax_number') ? trim((string) $request->input('tax_number')) : null);
        $telepon = $request->filled('telepon')
            ? trim((string) $request->input('telepon'))
            : ($request->filled('phone') ? trim((string) $request->input('phone')) : null);
        $alamat = $request->filled('alamat')
            ? trim((string) $request->input('alamat'))
            : ($request->filled('address') ? trim((string) $request->input('address')) : null);

        $file = $request->file('dokumen_npwp') ?? $request->file('legal_document') ?? $request->file('file_npwp');
        $documentPath = null;

        if ($file instanceof UploadedFile) {
            $extension = strtolower($file->guessExtension() ?: 'bin');
            $filename = Str::uuid()->toString().'.'.$extension;

            $documentPath = $file->storeAs(
                'perusahaan/legalitas',
                $filename,
                'local'
            );

            if ($documentPath === false) {
                throw new RuntimeException('Dokumen legalitas gagal disimpan.');
            }
        }

        try {
            $user = DB::transaction(function () use ($name, $email, $password, $googleId, $avatar, $namaPerusahaan, $nomorNpwp, $telepon, $alamat, $documentPath): User {
                $perusahaan = $this->perusahaanBuyerRepository->create([
                    'company_name' => $namaPerusahaan,
                    'tax_number' => $nomorNpwp,
                    'phone' => $telepon,
                    'legal_document_path' => $documentPath,
                    'address' => $alamat,
                    'verification_status' => VerificationStatus::Pending,
                    'is_active' => true,
                ]);

                $ownerRole = Role::where('name', RoleName::Owner->value)->first()
                    ?? Role::where('name', 'owner')->first()
                    ?? Role::where('name', 'purchasing')->first();
                $roleId = $ownerRole ? (int) $ownerRole->getKey() : 6;

                return $this->userRepository->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'google_id' => $googleId ?: null,
                    'avatar' => $avatar ?: null,
                    'role_id' => $roleId,
                    'tenant_id' => (int) $perusahaan->getKey(),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);
            });
        } catch (QueryException $e) {
            if ($documentPath !== null) {
                Storage::disk('local')->delete($documentPath);
            }

            $message = $e->getMessage();

            if (
                str_contains($message, 'ux_tenants_tax_number')
                || (str_contains($message, 'tax_number') && (str_contains($message, 'UNIQUE') || str_contains($message, 'duplicate') || str_contains($message, 'Integrity constraint violation')))
            ) {
                throw ValidationException::withMessages([
                    'nomor_npwp' => 'Nomor NPWP ini sudah terdaftar dalam sistem.',
                ]);
            }

            if (
                str_contains($message, 'ux_users_email')
                || (str_contains($message, 'email') && (str_contains($message, 'UNIQUE') || str_contains($message, 'duplicate') || str_contains($message, 'Integrity constraint violation')))
            ) {
                throw ValidationException::withMessages([
                    'email' => 'Alamat email ini sudah terdaftar dalam sistem.',
                ]);
            }

            throw $e;
        } catch (Throwable $e) {
            if ($documentPath !== null) {
                Storage::disk('local')->delete($documentPath);
            }

            throw $e;
        }

        if ($isGoogleRegistration && $defaultPassword !== null) {
            try {
                $user->notify(new GoogleRegistrationPasswordNotification(
                    $user,
                    $defaultPassword,
                    'Tenant (Perusahaan Pembeli)'
                ));
            } catch (Throwable $e) {
                Log::error('Gagal mengirimkan email notifikasi kata sandi Google', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }

            session()->forget(['google_user', 'google_auth_flow']);
        }

        return [
            'success' => true,
            'code' => 201,
            'message' => 'Pendaftaran akun tenant berhasil. Akun dan dokumen legalitas Anda sedang menunggu verifikasi administrator.',
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
        $email = strtolower(trim((string) $googleUser->getEmail()));
        $name = (string) ($googleUser->getName() ?: $googleUser->getNickname() ?: 'Pengguna');
        $avatar = (string) ($googleUser->getAvatar() ?? '');

        // 1. Cek apakah ada akun soft-deleted dengan email atau google_id tersebut
        $deletedUser = User::onlyTrashed()
            ->where(function ($query) use ($googleId, $email) {
                $query->where('google_id', $googleId)
                    ->orWhere('email', $email);
            })
            ->first();

        if ($deletedUser) {
            return [
                'success' => false,
                'code' => 403,
                'message' => 'Akun dinonaktifkan.',
                'errors' => [
                    'google' => 'Akun Anda telah dinonaktifkan atau dihapus. Silakan hubungi administrator.',
                ],
            ];
        }

        // 2. Cari akun aktif
        $user = $this->userRepository->findByGoogleId($googleId);

        if (! $user && ! empty($email)) {
            $user = $this->userRepository->findByEmail($email);
            if ($user) {
                $this->userRepository->updateGoogleId($user->id, $googleId, $avatar);
                $user->refresh();
            }
        }

        // 3. Jika user sudah ada (Login):
        if ($user) {
            if ($user->email_verified_at === null) {
                $user->email_verified_at = now();
                $user->is_active = true;
                $user->save();
            }

            if (! $user->is_active) {
                return [
                    'success' => false,
                    'code' => 403,
                    'message' => 'Akun dinonaktifkan.',
                    'errors' => [
                        'google' => 'Akun Anda dinonaktifkan. Silakan hubungi administrator.',
                    ],
                ];
            }

            if ($companyBlock = $this->checkCompanyVerificationAndStatus($user, 'google')) {
                return $companyBlock;
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

        // 4. Jika user belum ada:
        $flow = session('google_auth_flow', 'tenant');

        if ($flow === 'login') {
            return [
                'success' => false,
                'code' => 404,
                'message' => 'Akun Google belum terdaftar.',
                'errors' => [
                    'google' => 'Akun Google ini belum terdaftar dalam sistem PAGORA. Silakan lakukan pendaftaran terlebih dahulu.',
                ],
            ];
        }

        $googleData = [
            'google_id' => $googleId,
            'email' => $email,
            'name' => $name,
            'avatar' => $avatar,
        ];
        session(['google_user' => $googleData]);

        return [
            'success' => true,
            'code' => 200,
            'is_new_registration' => true,
            'registration_flow' => $flow,
            'message' => 'Akun Google berhasil terhubung. Silakan lengkapi data perusahaan.',
            'google_user' => $googleData,
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

    /**
     * Ensure the user's tenant or vendor company is active and approved (PRD BR-21).
     *
     * @return array{success: bool, code: int, message: string, errors: array<string, string>}|null
     */
    private function checkCompanyVerificationAndStatus(User $user, string $errorField = 'email'): ?array
    {
        if ($user->tenant_id !== null) {
            $tenant = $user->perusahaanBuyer;

            if ($tenant && $tenant->verification_status !== VerificationStatus::Approved) {
                $message = $tenant->verification_status === VerificationStatus::Rejected
                    ? 'Pendaftaran perusahaan Anda ditolak oleh administrator.'
                    : 'Akun perusahaan Anda sedang menunggu verifikasi oleh administrator.';

                return [
                    'success' => false,
                    'code' => 403,
                    'message' => 'Akun ditangguhkan.',
                    'errors' => [
                        $errorField => $message,
                    ],
                ];
            }

            if ($tenant && ! $tenant->is_active) {
                return [
                    'success' => false,
                    'code' => 403,
                    'message' => 'Akun ditangguhkan.',
                    'errors' => [
                        $errorField => 'Perusahaan Anda telah dinonaktifkan. Silakan hubungi administrator.',
                    ],
                ];
            }
        }

        if ($user->vendor_id !== null) {
            $vendor = $user->vendor;

            if ($vendor && $vendor->verification_status !== VerificationStatus::Approved) {
                $message = $vendor->verification_status === VerificationStatus::Rejected
                    ? 'Pendaftaran vendor Anda ditolak oleh administrator.'
                    : 'Akun vendor Anda sedang menunggu verifikasi oleh administrator.';

                return [
                    'success' => false,
                    'code' => 403,
                    'message' => 'Akun ditangguhkan.',
                    'errors' => [
                        $errorField => $message,
                    ],
                ];
            }

            if ($vendor && ! $vendor->is_active) {
                return [
                    'success' => false,
                    'code' => 403,
                    'message' => 'Akun ditangguhkan.',
                    'errors' => [
                        $errorField => 'Akun vendor Anda telah ditangguhkan secara global.',
                    ],
                ];
            }
        }

        return null;
    }
}
