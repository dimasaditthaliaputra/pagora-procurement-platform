<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PengaturanService;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Display the home landing page.
     */
    public function index(): Response
    {
        return Inertia::render('Home', [
            'message' => 'Selamat datang di PAGORA — Ekosistem Pengadaan Bahan Baku B2B.',
        ]);
    }

    /**
     * Display the public Terms and Conditions (Syarat dan Ketentuan) page.
     */
    public function syaratKetentuan(PengaturanService $pengaturanService): Response
    {
        $jsonPath = base_path('docs/terms-and-conditions.json');
        $raw = File::exists($jsonPath)
            ? json_decode((string) File::get($jsonPath), true)
            : [];

        $profil = $pengaturanService->profilPublik();
        $appName = (string) ($profil['nama'] ?? config('app.name', 'PAGORA'));
        $contactEmail = (string) config('mail.from.address', 'support@pagora.id');

        $replacements = [
            '{{TANGGAL_BERLAKU}}' => '1 November 2026',
            '{{NAMA_PENYELENGGARA}}' => $appName,
            '{{EMAIL_KONTAK}}' => $contactEmail,
            '{{YURISDIKSI}}' => 'Pengadilan Negeri di wilayah hukum Republik Indonesia',
        ];

        $replacePlaceholders = static function (string $value) use ($replacements): string {
            return strtr($value, $replacements);
        };

        $rawMeta = is_array($raw['meta'] ?? null) ? $raw['meta'] : [];
        $meta = [
            'title' => $replacePlaceholders((string) ($rawMeta['title'] ?? 'Syarat dan Ketentuan Penggunaan PAGORA')),
            'version' => (string) ($rawMeta['version'] ?? '1.0'),
            'effective_date' => $replacePlaceholders((string) ($rawMeta['effective_date'] ?? '1 November 2026')),
            'language' => (string) ($rawMeta['language'] ?? 'id'),
            'operator_name' => $replacePlaceholders((string) ($rawMeta['operator_name'] ?? $appName)),
            'contact_email' => $replacePlaceholders((string) ($rawMeta['contact_email'] ?? $contactEmail)),
            'jurisdiction' => $replacePlaceholders((string) ($rawMeta['jurisdiction'] ?? 'Pengadilan Negeri di wilayah hukum Republik Indonesia')),
        ];

        $rawSections = is_array($raw['sections'] ?? null) ? $raw['sections'] : [];
        $sections = array_values(array_map(static function (array $section) use ($replacePlaceholders): array {
            $clauses = is_array($section['clauses'] ?? null) ? $section['clauses'] : [];

            return [
                'number' => (int) ($section['number'] ?? 0),
                'id' => (string) ($section['id'] ?? ''),
                'title' => $replacePlaceholders((string) ($section['title'] ?? '')),
                'clauses' => array_values(array_map(
                    static fn (mixed $clause): string => $replacePlaceholders((string) $clause),
                    $clauses
                )),
            ];
        }, $rawSections));

        $rawAcceptance = is_array($raw['acceptance'] ?? null) ? $raw['acceptance'] : [];
        $acceptance = [
            'checkbox_label' => $replacePlaceholders((string) ($rawAcceptance['checkbox_label'] ?? 'Saya telah membaca dan menyetujui Syarat dan Ketentuan PAGORA.')),
            'applies_to' => is_array($rawAcceptance['applies_to'] ?? null) ? array_values($rawAcceptance['applies_to']) : [],
        ];

        return Inertia::render('Home/SyaratKetentuan', [
            'meta' => $meta,
            'sections' => $sections,
            'acceptance' => $acceptance,
        ]);
    }
}
