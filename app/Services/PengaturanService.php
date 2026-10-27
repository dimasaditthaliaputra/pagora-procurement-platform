<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Pengaturan;
use App\Repositories\Contracts\PengaturanRepositoryInterface;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PengaturanService
{
    private const CACHE_KEY = 'pengaturan.semua';

    private const LOGO_DISK = 'public';

    private const LOGO_DIRECTORY = 'pengaturan';

    public const GRUP_PROFIL = 'profil';

    public const GRUP_INTEGRASI = 'integrasi';

    public const PROVIDER_OPTIONS = ['midtrans', 'xendit'];

    public const MODE_OPTIONS = ['sandbox', 'production'];

    public const JADWAL_SINKRON_OPTIONS = ['harian', 'mingguan', 'manual'];

    /**
     * Registry of every application setting.
     * SMTP and Google OAuth intentionally stay in .env and are not listed here.
     *
     * @var array<string, array{grup: string, tipe: string, default: mixed, terenkripsi?: bool}>
     */
    public const DEFINISI = [
        // Profil Aplikasi
        'nama_aplikasi' => ['grup' => self::GRUP_PROFIL, 'tipe' => 'string', 'default' => 'PAGORA'],
        'tagline' => ['grup' => self::GRUP_PROFIL, 'tipe' => 'string', 'default' => 'E-Procurement'],
        'logo' => ['grup' => self::GRUP_PROFIL, 'tipe' => 'file', 'default' => null],

        // Integrasi API Bank Indonesia
        'bi_aktif' => ['grup' => self::GRUP_INTEGRASI, 'tipe' => 'boolean', 'default' => true],
        'bi_base_url' => ['grup' => self::GRUP_INTEGRASI, 'tipe' => 'string', 'default' => 'https://www.bi.go.id/hargapangan'],
        'bi_jadwal_sinkron' => ['grup' => self::GRUP_INTEGRASI, 'tipe' => 'string', 'default' => 'harian'],

        // Integrasi Payment Gateway
        'pg_provider' => ['grup' => self::GRUP_INTEGRASI, 'tipe' => 'string', 'default' => 'midtrans'],
        'pg_mode' => ['grup' => self::GRUP_INTEGRASI, 'tipe' => 'string', 'default' => 'sandbox'],
        'pg_client_key' => ['grup' => self::GRUP_INTEGRASI, 'tipe' => 'string', 'default' => null],
        'pg_server_key' => ['grup' => self::GRUP_INTEGRASI, 'tipe' => 'string', 'default' => null, 'terenkripsi' => true],
        'pg_webhook_token' => ['grup' => self::GRUP_INTEGRASI, 'tipe' => 'string', 'default' => null, 'terenkripsi' => true],
    ];

    public function __construct(
        protected PengaturanRepositoryInterface $repository
    ) {}

    /**
     * Get a single setting value (decrypted and typed), falling back to its default.
     */
    public function get(string $kunci, mixed $default = null): mixed
    {
        if (! array_key_exists($kunci, self::DEFINISI)) {
            return $default;
        }

        $row = $this->rows()->get($kunci);

        if ($row === null || $row['nilai'] === null) {
            return self::DEFINISI[$kunci]['default'] ?? $default;
        }

        $nilai = $row['terenkripsi'] ? $this->decrypt($kunci, $row['nilai']) : $row['nilai'];

        return $nilai === null ? self::DEFINISI[$kunci]['default'] : $this->cast($kunci, $nilai);
    }

    /**
     * Get every value in a group, keyed by kunci.
     *
     * @return array<string, mixed>
     */
    public function grup(string $grup): array
    {
        $hasil = [];

        foreach (self::DEFINISI as $kunci => $definisi) {
            if ($definisi['grup'] === $grup) {
                $hasil[$kunci] = $this->get($kunci);
            }
        }

        return $hasil;
    }

    /**
     * Get a group's values safe to send to the browser.
     * Secrets are never returned; only a `{kunci}_terisi` flag is exposed.
     *
     * @return array<string, mixed>
     */
    public function grupUntukForm(string $grup): array
    {
        $hasil = [];

        foreach ($this->grup($grup) as $kunci => $nilai) {
            if (self::DEFINISI[$kunci]['terenkripsi'] ?? false) {
                $hasil[$kunci.'_terisi'] = $nilai !== null && $nilai !== '';

                continue;
            }

            $hasil[$kunci] = $nilai;
        }

        if ($grup === self::GRUP_PROFIL) {
            $hasil['logo_url'] = $this->logoUrl($hasil['logo'] ?? null);
            unset($hasil['logo']);
        }

        return $hasil;
    }

    /**
     * Public app identity shared to every Inertia page.
     *
     * @return array{nama: string, tagline: string|null, logo_url: string|null}
     */
    public function profilPublik(): array
    {
        return [
            'nama' => (string) $this->get('nama_aplikasi'),
            'tagline' => $this->get('tagline'),
            'logo_url' => $this->logoUrl($this->get('logo')),
        ];
    }

    /**
     * Update the application profile, including optional logo upload or removal.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProfil(array $data, ?UploadedFile $logo, bool $hapusLogo, ?int $userId): void
    {
        $logoLama = $this->get('logo');
        $logoBaru = $logo?->store(self::LOGO_DIRECTORY, self::LOGO_DISK);

        if ($logoBaru !== null && $logoBaru !== false) {
            $data['logo'] = $logoBaru;
        } elseif ($hapusLogo) {
            $data['logo'] = null;
        }

        try {
            $this->simpan(self::GRUP_PROFIL, $data, $userId);
        } catch (\Throwable $e) {
            if (is_string($logoBaru)) {
                Storage::disk(self::LOGO_DISK)->delete($logoBaru);
            }

            throw $e;
        }

        if (array_key_exists('logo', $data) && is_string($logoLama) && $logoLama !== $data['logo']) {
            Storage::disk(self::LOGO_DISK)->delete($logoLama);
        }
    }

    /**
     * Update integration settings. Empty secret fields keep the stored value.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateIntegrasi(array $data, ?int $userId): void
    {
        foreach (self::DEFINISI as $kunci => $definisi) {
            if (($definisi['terenkripsi'] ?? false) && blank($data[$kunci] ?? null)) {
                unset($data[$kunci]);
            }
        }

        $this->simpan(self::GRUP_INTEGRASI, $data, $userId);
    }

    /**
     * Persist values belonging to a group and flush the cache.
     *
     * @param  array<string, mixed>  $data
     */
    private function simpan(string $grup, array $data, ?int $userId): void
    {
        $data = array_filter(
            $data,
            fn (string $kunci): bool => (self::DEFINISI[$kunci]['grup'] ?? null) === $grup,
            ARRAY_FILTER_USE_KEY
        );

        DB::transaction(function () use ($data, $userId): void {
            foreach ($data as $kunci => $nilai) {
                $terenkripsi = self::DEFINISI[$kunci]['terenkripsi'] ?? false;

                $this->repository->upsert($kunci, [
                    'grup' => self::DEFINISI[$kunci]['grup'],
                    'nilai' => $this->serialize($kunci, $nilai, $terenkripsi),
                    'terenkripsi' => $terenkripsi,
                    'diubah_oleh' => $userId,
                ]);
            }
        });

        Cache::forget(self::CACHE_KEY);

        // Audit trail: record which keys changed, never their values.
        Log::info('Pengaturan aplikasi diperbarui.', [
            'grup' => $grup,
            'kunci' => array_keys($data),
            'user_id' => $userId,
        ]);
    }

    /**
     * Raw rows from cache. Secrets stay encrypted inside the cache store.
     *
     * @return Collection<string, array{nilai: string|null, terenkripsi: bool}>
     */
    private function rows(): Collection
    {
        try {
            // Cache a plain array: config/cache.php disallows unserializing objects
            // (serializable_classes = false), so a cached Collection would come back broken.
            $rows = Cache::rememberForever(self::CACHE_KEY, fn (): array => $this->repository->allKeyed()
                ->map(fn (Pengaturan $row): array => [
                    'nilai' => $row->nilai,
                    'terenkripsi' => $row->terenkripsi,
                ])
                ->all());

            return collect(is_array($rows) ? $rows : []);
        } catch (QueryException $e) {
            // Database unreachable or not migrated yet: fall back to defaults so every page
            // (including error pages, which share the app profile) can still render.
            Log::warning('Gagal memuat pengaturan, memakai nilai default.', ['error' => $e->getMessage()]);

            return collect();
        }
    }

    private function serialize(string $kunci, mixed $nilai, bool $terenkripsi): ?string
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        $string = self::DEFINISI[$kunci]['tipe'] === 'boolean'
            ? ($nilai ? '1' : '0')
            : (string) $nilai;

        return $terenkripsi ? Crypt::encryptString($string) : $string;
    }

    private function cast(string $kunci, string $nilai): mixed
    {
        return match (self::DEFINISI[$kunci]['tipe']) {
            'boolean' => $nilai === '1',
            default => $nilai,
        };
    }

    private function decrypt(string $kunci, string $nilai): ?string
    {
        try {
            return Crypt::decryptString($nilai);
        } catch (DecryptException) {
            // Happens when APP_KEY was rotated; treat as empty so it can be re-entered.
            Log::warning('Gagal mendekripsi pengaturan.', ['kunci' => $kunci]);

            return null;
        }
    }

    private function logoUrl(?string $path): ?string
    {
        return $path ? Storage::disk(self::LOGO_DISK)->url($path) : null;
    }
}
