<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\OtpMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public const PURPOSE_VENDOR_REGISTRATION = 'vendor_registration';

    public const PURPOSE_LOGIN = 'login';

    private const TTL_MINUTES = 10;

    private const RESEND_COOLDOWN_SECONDS = 60;

    private const MAX_ATTEMPTS = 5;

    /**
     * @var array<string, string>
     */
    private const PURPOSE_LABELS = [
        self::PURPOSE_VENDOR_REGISTRATION => 'memverifikasi email pendaftaran vendor Anda',
        self::PURPOSE_LOGIN => 'mengaktifkan akun dan masuk ke sistem',
    ];

    /**
     * Generate a one-time code, store its hash, and email it.
     *
     * @throws ValidationException when the resend cooldown is still running
     */
    public function send(string $purpose, string $email, string $errorField = 'email'): void
    {
        $key = $this->key($purpose, $email);
        $cooldownKey = $key.':cooldown';

        if (Cache::has($cooldownKey)) {
            $seconds = max(1, (int) Cache::get($cooldownKey) - time());

            throw ValidationException::withMessages([
                $errorField => "Mohon tunggu {$seconds} detik sebelum meminta kode baru.",
            ]);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($key, ['hash' => Hash::make($code), 'attempts' => 0], now()->addMinutes(self::TTL_MINUTES));
        Cache::put($cooldownKey, time() + self::RESEND_COOLDOWN_SECONDS, self::RESEND_COOLDOWN_SECONDS);

        Mail::to($email)->send(new OtpMail($code, self::PURPOSE_LABELS[$purpose], self::TTL_MINUTES));
    }

    /**
     * Verify a submitted code. The code is consumed on success.
     *
     * @throws ValidationException
     */
    public function verify(string $purpose, string $email, string $code, string $errorField = 'otp'): void
    {
        $key = $this->key($purpose, $email);
        $entry = Cache::get($key);

        if (! is_array($entry)) {
            throw ValidationException::withMessages([
                $errorField => 'Kode OTP tidak ditemukan atau sudah kedaluwarsa. Silakan minta kode baru.',
            ]);
        }

        if ($entry['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            throw ValidationException::withMessages([
                $errorField => 'Terlalu banyak percobaan. Silakan minta kode baru.',
            ]);
        }

        if (! Hash::check($code, $entry['hash'])) {
            $entry['attempts']++;
            Cache::put($key, $entry, now()->addMinutes(self::TTL_MINUTES));

            throw ValidationException::withMessages([
                $errorField => 'Kode OTP salah.',
            ]);
        }

        Cache::forget($key);
        Cache::forget($key.':cooldown');
    }

    /**
     * Seconds until a new code may be requested (0 when allowed).
     */
    public function cooldownRemaining(string $purpose, string $email): int
    {
        $until = Cache::get($this->key($purpose, $email).':cooldown');

        return $until === null ? 0 : max(0, (int) $until - time());
    }

    private function key(string $purpose, string $email): string
    {
        return 'otp:'.$purpose.':'.sha1(strtolower(trim($email)));
    }
}
