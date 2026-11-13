<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Helpers\ResponseHelper;
use App\Http\Middleware\AssignRequestId;
use App\Models\CommodityCategory;
use App\Models\User;
use App\Models\Vendor;
use App\Providers\AppServiceProvider;
use App\Repositories\Contracts\VendorRepositoryInterface;
use App\Services\FileStorageService;
use App\Services\KomoditasService;
use App\Services\OtpService;
use App\Services\VendorRegistrationService;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class ApplicationHelpersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_every_response_carries_a_request_id(): void
    {
        $this->get('/login')->assertHeader(AssignRequestId::HEADER);
    }

    public function test_valid_incoming_request_id_is_reused_and_invalid_one_replaced(): void
    {
        $this->withHeader(AssignRequestId::HEADER, 'trace-12345678')
            ->get('/login')
            ->assertHeader(AssignRequestId::HEADER, 'trace-12345678');

        $replaced = $this->withHeader(AssignRequestId::HEADER, '<script>')->get('/login')->headers->get(AssignRequestId::HEADER);
        $this->assertNotSame('<script>', $replaced);
    }

    public function test_sensitive_input_is_never_flashed_back_to_the_session(): void
    {
        Route::post('/_test/forbidden', fn () => ResponseHelper::forbidden('Ditolak.'))->middleware('web');

        $this->from('/profil')->post('/_test/forbidden', [
            'name' => 'Budi',
            'password' => 'rahasia-123',
            'password_saat_ini' => 'lama-123',
            'pg_server_key' => 'SB-Mid-server-xxx',
        ])->assertRedirect('/profil')
            ->assertSessionHas('error', 'Ditolak.')
            ->assertSessionHasInput('name', 'Budi')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_saat_ini')
            ->assertSessionMissing('_old_input.pg_server_key');
    }

    public function test_server_error_flashes_a_generic_message_for_page_requests(): void
    {
        Route::post('/_test/server-error', fn () => ResponseHelper::serverError('Gagal simpan', new RuntimeException('SQL detail rahasia')))
            ->middleware('web');

        $this->from('/profil')->post('/_test/server-error')
            ->assertRedirect('/profil')
            ->assertSessionHas('error', 'Terjadi kesalahan pada server. Silakan coba beberapa saat lagi.')
            ->assertSessionHasErrors('general');
    }

    public function test_server_error_json_hides_exception_details_unless_debug(): void
    {
        Route::post('/_test/server-error', fn () => ResponseHelper::serverError('Gagal simpan', new RuntimeException('SQL detail rahasia')))
            ->middleware('web');

        config(['app.debug' => false]);
        $this->postJson('/_test/server-error')
            ->assertStatus(500)
            ->assertJsonMissingPath('debug')
            ->assertDontSee('SQL detail rahasia');

        config(['app.debug' => true]);
        $this->postJson('/_test/server-error')
            ->assertStatus(500)
            ->assertJsonPath('debug.exception', RuntimeException::class)
            ->assertJsonPath('debug.message', 'SQL detail rahasia');
    }

    public function test_expired_session_redirects_back_with_a_message(): void
    {
        Route::post('/_test/expired', fn () => throw new TokenMismatchException)->middleware('web');

        $this->from('/profil')->post('/_test/expired')
            ->assertRedirect('/profil')
            ->assertSessionHas('error', 'Sesi halaman telah berakhir. Silakan coba lagi.');
    }

    public function test_throttled_page_renders_the_error_page(): void
    {
        Route::get('/_test/throttled', fn () => abort(429))->middleware('web');

        $this->get('/_test/throttled')
            ->assertStatus(429)
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 429));
    }

    public function test_production_500_page_shows_the_request_id(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/crash', fn () => throw new RuntimeException('boom'))->middleware('web');

        $response = $this->get('/_test/crash')->assertStatus(500);
        $requestId = $response->headers->get(AssignRequestId::HEADER);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('request_id', $requestId)
        );
    }

    public function test_stored_file_is_removed_when_the_database_write_fails(): void
    {
        Storage::fake('public');
        $storage = app(FileStorageService::class);
        $path = $storage->store(UploadedFile::fake()->image('logo.png'), 'logo', 'public');

        try {
            $storage->rollbackOnFailure('public', $path, fn () => throw new RuntimeException('DB down'));
            $this->fail('Exception should be rethrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('DB down', $e->getMessage());
        }

        Storage::disk('public')->assertMissing($path);
    }

    public function test_delete_ignores_external_urls_and_empty_values(): void
    {
        Storage::fake('public');
        $storage = app(FileStorageService::class);

        $storage->delete('public', 'https://lh3.googleusercontent.com/a/avatar.jpg');
        $storage->delete('public', null);
        $storage->delete('public', '');

        $this->assertFalse($storage->isStoredPath('https://lh3.googleusercontent.com/a/avatar.jpg'));
        $this->assertTrue($storage->isStoredPath('avatar/foto.png'));
    }

    public function test_vendor_document_is_cleaned_up_when_registration_fails(): void
    {
        Storage::fake('local');

        $category = CommodityCategory::factory()->create(['is_active' => true]);

        $this->mock(OtpService::class)->shouldReceive('verify')->andReturnNull();
        $this->mock(VendorRepositoryInterface::class)
            ->shouldReceive('create')
            ->andThrow(new RuntimeException('DB down'));

        try {
            app(VendorRegistrationService::class)->register([
                'name' => 'Andi Uji',
                'email' => 'andi@vendor.test',
                'password' => 'password123',
                'company_name' => 'PT Uji',
                'tax_number' => '01.234.567.8-901.000',
                'contact_email' => 'uji@vendor.test',
                'phone' => '081234567890',
                'address' => 'Bekasi',
                'commodity_category_uuids' => [$category->uuid],
                'otp' => '123456',
                'legal_documents' => [
                    [
                        'document_type' => 'npwp',
                        'file' => UploadedFile::fake()->create('npwp.pdf', 100, 'application/pdf'),
                    ],
                    [
                        'document_type' => 'nib',
                        'file' => UploadedFile::fake()->create('nib.pdf', 100, 'application/pdf'),
                    ],
                ],
            ]);
            $this->fail('Exception should be rethrown.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame([], Storage::disk('local')->allFiles('vendors/dokumen'));
        $this->assertSame(0, Vendor::count());
    }

    public function test_vendor_registration_rejects_invalid_phone_number(): void
    {
        $this->from('/register/vendor')->post('/register/vendor', [
            'company_name' => 'PT Uji',
            'contact_email' => 'uji@vendor.test',
            'phone' => 'nomor-saya',
            'address' => 'Bekasi',
            'kategori_komoditas' => 'Lainnya',
            'otp' => '123456',
        ])->assertSessionHasErrors(['phone' => 'Format nomor telepon tidak valid. Gunakan 8–15 digit angka, contoh: 081234567890.']);
    }

    public function test_avatar_is_replaced_and_old_file_deleted(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar' => 'avatar/lama.png']);
        Storage::disk('public')->put('avatar/lama.png', 'x');

        $this->actingAs($user)->post('/profil', [
            '_method' => 'put',
            'name' => $user->name,
            'avatar' => UploadedFile::fake()->image('baru.png'),
        ])->assertSessionHasNoErrors();

        $newPath = $user->fresh()->avatar;
        Storage::disk('public')->assertMissing('avatar/lama.png');
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_destructive_commands_are_blocked_only_in_production(): void
    {
        $this->app['env'] = 'production';
        (new AppServiceProvider($this->app))->boot();

        try {
            // Runs against the in-memory test database, so nothing real is at risk.
            $this->artisan('db:wipe', ['--force' => true])->assertFailed();
        } finally {
            DB::prohibitDestructiveCommands(false);
            $this->app['env'] = 'testing';
        }
    }

    public function test_lazy_loading_diagnostics_stay_off_outside_local(): void
    {
        $this->assertFalse(Model::preventsLazyLoading());
    }

    public function test_uncaught_failure_on_a_list_page_is_handled_by_the_global_handler(): void
    {
        config(['app.debug' => false]);
        $this->mock(KomoditasService::class)
            ->shouldReceive('paginate')
            ->andThrow(new RuntimeException('SQL detail rahasia'));

        $superadmin = User::factory()->create(['role_id' => 1]);

        $this->actingAs($superadmin)->get('/master/komoditas')
            ->assertStatus(500)
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 500))
            ->assertDontSee('SQL detail rahasia');

        $this->actingAs($superadmin)->getJson('/master/komoditas')
            ->assertStatus(500)
            ->assertDontSee('SQL detail rahasia');
    }
}
