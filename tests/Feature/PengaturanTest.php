<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Pengaturan;
use App\Models\User;
use App\Services\PengaturanService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PengaturanTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->superadmin = User::factory()->create([
            'role_id' => 1, // Superadmin
            'status_aktif' => true,
        ]);

        $this->buyer = User::factory()->create([
            'role_id' => 2, // Buyer
            'status_aktif' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function integrasiPayload(array $overrides = []): array
    {
        return array_merge([
            'bi_aktif' => true,
            'bi_base_url' => 'https://www.bi.go.id/hargapangan',
            'bi_jadwal_sinkron' => 'harian',
            'pg_provider' => 'midtrans',
            'pg_mode' => 'sandbox',
            'pg_client_key' => 'SB-Mid-client-abc',
            'pg_server_key' => '',
            'pg_webhook_token' => '',
        ], $overrides);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/pengaturan/profil')->assertRedirect('/login');
        $this->put('/pengaturan/integrasi', $this->integrasiPayload())->assertRedirect('/login');
    }

    public function test_non_superadmin_cannot_access_pengaturan(): void
    {
        $this->actingAs($this->buyer)->get('/pengaturan/profil')->assertForbidden();
        $this->actingAs($this->buyer)->get('/pengaturan/integrasi')->assertForbidden();
        $this->actingAs($this->buyer)
            ->put('/pengaturan/integrasi', $this->integrasiPayload())
            ->assertForbidden();
    }

    public function test_profil_page_shows_defaults_when_nothing_saved(): void
    {
        $this->actingAs($this->superadmin)
            ->get('/pengaturan/profil')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Pengaturan/Profil')
                ->where('pengaturan.nama_aplikasi', 'PAGORA')
                ->where('pengaturan.tagline', 'E-Procurement')
                ->where('pengaturan.logo_url', null)
                ->where('aplikasi.nama', 'PAGORA'));
    }

    public function test_superadmin_can_update_profil_with_logo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->superadmin)
            ->post('/pengaturan/profil', [
                'nama_aplikasi' => 'PAGORA Pro',
                'tagline' => 'Pengadaan Bahan Baku',
                'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            ])
            ->assertRedirect('/pengaturan/profil')
            ->assertSessionHas('success');

        $service = app(PengaturanService::class);
        $this->assertSame('PAGORA Pro', $service->get('nama_aplikasi'));
        $this->assertSame('Pengadaan Bahan Baku', $service->get('tagline'));

        $logo = $service->get('logo');
        $this->assertNotNull($logo);
        Storage::disk('public')->assertExists($logo);

        $this->assertDatabaseHas('pengaturan', [
            'kunci' => 'nama_aplikasi',
            'diubah_oleh' => $this->superadmin->id,
        ]);

        $this->get('/pengaturan/profil')->assertInertia(fn (Assert $page) => $page
            ->where('aplikasi.nama', 'PAGORA Pro')
            ->where('aplikasi.tagline', 'Pengadaan Bahan Baku'));
    }

    public function test_replacing_and_removing_logo_deletes_old_file(): void
    {
        Storage::fake('public');
        $service = app(PengaturanService::class);

        $this->actingAs($this->superadmin)->post('/pengaturan/profil', [
            'nama_aplikasi' => 'PAGORA',
            'logo' => UploadedFile::fake()->image('lama.png'),
        ]);
        $logoLama = $service->get('logo');

        $this->post('/pengaturan/profil', [
            'nama_aplikasi' => 'PAGORA',
            'logo' => UploadedFile::fake()->image('baru.png'),
        ]);
        $logoBaru = $service->get('logo');

        Storage::disk('public')->assertMissing($logoLama);
        Storage::disk('public')->assertExists($logoBaru);

        $this->post('/pengaturan/profil', [
            'nama_aplikasi' => 'PAGORA',
            'hapus_logo' => true,
        ]);

        $this->assertNull($service->get('logo'));
        Storage::disk('public')->assertMissing($logoBaru);
    }

    public function test_profil_validation(): void
    {
        $this->actingAs($this->superadmin)
            ->post('/pengaturan/profil', [
                'nama_aplikasi' => '',
                'tagline' => str_repeat('a', 101),
                'logo' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors(['nama_aplikasi', 'tagline', 'logo']);
    }

    public function test_secret_is_stored_encrypted_and_never_sent_to_browser(): void
    {
        $this->actingAs($this->superadmin)
            ->put('/pengaturan/integrasi', $this->integrasiPayload([
                'pg_server_key' => 'SB-Mid-server-rahasia',
            ]))
            ->assertRedirect('/pengaturan/integrasi')
            ->assertSessionHas('success');

        $row = Pengaturan::where('kunci', 'pg_server_key')->firstOrFail();
        $this->assertTrue($row->terenkripsi);
        $this->assertNotSame('SB-Mid-server-rahasia', $row->nilai);
        $this->assertSame('SB-Mid-server-rahasia', Crypt::decryptString($row->nilai));
        $this->assertSame('SB-Mid-server-rahasia', app(PengaturanService::class)->get('pg_server_key'));

        $response = $this->get('/pengaturan/integrasi');
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Pengaturan/Integrasi')
            ->where('pengaturan.pg_server_key_terisi', true)
            ->where('pengaturan.pg_webhook_token_terisi', false)
            ->missing('pengaturan.pg_server_key')
            ->where('pengaturan.pg_client_key', 'SB-Mid-client-abc'));
        $this->assertStringNotContainsString('SB-Mid-server-rahasia', $response->getContent());
    }

    public function test_empty_secret_keeps_existing_value(): void
    {
        $this->actingAs($this->superadmin)
            ->put('/pengaturan/integrasi', $this->integrasiPayload(['pg_server_key' => 'kunci-awal']));

        $this->put('/pengaturan/integrasi', $this->integrasiPayload([
            'pg_server_key' => '',
            'pg_mode' => 'production',
            'bi_aktif' => false,
        ]));

        $service = app(PengaturanService::class);
        $this->assertSame('kunci-awal', $service->get('pg_server_key'));
        $this->assertSame('production', $service->get('pg_mode'));
        $this->assertFalse($service->get('bi_aktif'));
    }

    public function test_integrasi_validation(): void
    {
        $this->actingAs($this->superadmin)
            ->put('/pengaturan/integrasi', $this->integrasiPayload([
                'bi_base_url' => 'http://tidak-aman.test',
                'pg_provider' => 'paypal',
                'pg_mode' => 'live',
                'bi_jadwal_sinkron' => 'tahunan',
            ]))
            ->assertSessionHasErrors(['bi_base_url', 'pg_provider', 'pg_mode', 'bi_jadwal_sinkron']);
    }

    public function test_settings_survive_a_serializing_cache_store(): void
    {
        // The array store skips serialization; the database store (used locally) does not.
        config(['cache.default' => 'database']);

        $this->actingAs($this->superadmin)
            ->put('/pengaturan/integrasi', $this->integrasiPayload(['pg_mode' => 'production']));

        // First read fills the cache, second read comes back from it.
        app(PengaturanService::class)->get('pg_mode');
        $this->assertSame('production', app(PengaturanService::class)->get('pg_mode'));
        $this->get('/pengaturan/profil')->assertOk();
    }

    public function test_unknown_keys_are_ignored(): void
    {
        $this->actingAs($this->superadmin)
            ->put('/pengaturan/integrasi', $this->integrasiPayload(['nama_aplikasi' => 'Disusupkan']));

        $this->assertDatabaseMissing('pengaturan', ['kunci' => 'nama_aplikasi']);
    }
}
