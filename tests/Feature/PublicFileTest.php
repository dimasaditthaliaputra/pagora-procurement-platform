<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicFileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_guest_can_fetch_avatar_with_cache_and_hardening_headers(): void
    {
        Storage::disk('public')->putFileAs('avatar', UploadedFile::fake()->image('a.jpg'), 'abc123.jpg');

        $response = $this->get('/files/avatar/abc123.jpg');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
    }

    public function test_guest_can_fetch_app_logo_from_pengaturan_directory(): void
    {
        Storage::disk('public')->putFileAs('pengaturan', UploadedFile::fake()->image('logo.png'), 'logo123.png');

        $this->get('/files/pengaturan/logo123.png')->assertOk();
    }

    public function test_static_file_response_does_not_start_a_session(): void
    {
        Storage::disk('public')->putFileAs('avatar', UploadedFile::fake()->image('a.jpg'), 'abc123.jpg');

        $response = $this->get('/files/avatar/abc123.jpg');

        $this->assertCount(0, $response->headers->getCookies());
    }

    public function test_missing_file_returns_not_found(): void
    {
        $this->get('/files/avatar/tidak-ada.jpg')->assertNotFound();
    }

    public function test_directories_outside_the_allow_list_are_not_served(): void
    {
        Storage::disk('public')->put('rahasia/data.txt', 'rahasia');

        $this->get('/files/rahasia/data.txt')->assertNotFound();
    }

    public function test_single_segment_path_is_not_served(): void
    {
        Storage::disk('public')->put('avatar', 'bukan-file-di-dalam-direktori');

        $this->get('/files/avatar')->assertNotFound();
    }

    public function test_path_traversal_is_rejected(): void
    {
        Storage::disk('public')->put('rahasia/data.txt', 'rahasia');

        $this->get('/files/avatar/../rahasia/data.txt')->assertNotFound();
        $this->get('/files/avatar/%2e%2e/rahasia/data.txt')->assertNotFound();
    }

    public function test_characters_outside_the_allowed_set_do_not_match_the_route(): void
    {
        $this->get('/files/avatar/a%00.jpg')->assertNotFound();
        $this->get('/files/avatar/a%5Cb.jpg')->assertNotFound();
    }

    public function test_storage_url_points_to_app_files_route_when_r2_public_url_is_empty(): void
    {
        config(['app.url' => 'https://pagora.example']);

        $this->assertSame('https://pagora.example/files', $this->publicDiskUrlFor('r2', '', 'https://pagora.example'));
    }

    public function test_storage_url_uses_r2_public_url_when_configured(): void
    {
        $this->assertSame(
            'https://cdn.example.com',
            $this->publicDiskUrlFor('r2', 'https://cdn.example.com/', 'https://pagora.example'),
        );
    }

    /**
     * Evaluate config/filesystems.php under the given environment and return the public disk URL.
     */
    private function publicDiskUrlFor(string $storage, string $r2PublicUrl, string $appUrl): ?string
    {
        $env = ['UPLOAD_STORAGE' => $storage, 'R2_PUBLIC_URL' => $r2PublicUrl, 'APP_URL' => $appUrl];
        $original = array_map(fn (string $key) => $_ENV[$key] ?? null, array_keys($env));

        foreach ($env as $key => $value) {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }

        try {
            $config = require config_path('filesystems.php');

            return $config['disks']['public']['url'];
        } finally {
            foreach (array_keys($env) as $index => $key) {
                if ($original[$index] === null) {
                    unset($_ENV[$key], $_SERVER[$key]);
                    putenv($key);
                } else {
                    $_ENV[$key] = $_SERVER[$key] = $original[$index];
                    putenv("{$key}={$original[$index]}");
                }
            }
        }
    }
}
