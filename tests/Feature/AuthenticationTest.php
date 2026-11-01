<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PerusahaanBuyer;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Mockery\MockInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_login_page_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_login_success_with_valid_credentials_and_remember_me(): void
    {
        $user = User::factory()->create([
            'email' => 'buyer@pagora.id',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'buyer@pagora.id',
            'password' => 'password123',
            'remember' => true,
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $response->assertCookie(Auth::guard()->getRecallerName());
    }

    public function test_remember_me_cookie_expires_after_at_most_thirty_days(): void
    {
        User::factory()->create([
            'email' => 'buyer@pagora.id',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'buyer@pagora.id',
            'password' => 'password123',
            'remember' => true,
        ]);

        $cookie = $response->getCookie(Auth::guard()->getRecallerName(), false);

        $this->assertNotNull($cookie);
        /** @var Cookie $cookie */
        /** @var \Symfony\Component\HttpFoundation\Cookie $cookie */
        $this->assertEqualsWithDelta(now()->addDays(30)->getTimestamp(), $cookie->getExpiresTime(), 60);
    }

    public function test_login_fails_when_email_not_registered_and_does_not_create_user(): void
    {
        $initialUserCount = User::count();

        $response = $this->from('/login')->post('/login', [
            'email' => 'unregistered@pagora.id',
            'password' => 'password123',
            'remember' => false,
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
        $this->assertSame($initialUserCount, User::count());
    }

    public function test_login_fails_when_password_incorrect(): void
    {
        User::factory()->create([
            'email' => 'buyer@pagora.id',
            'password' => Hash::make('correct_password'),
            'is_active' => true,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'buyer@pagora.id',
            'password' => 'wrong_password',
            'remember' => false,
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['password']);
        $this->assertGuest();
    }

    public function test_login_validation_errors_for_empty_fields_and_invalid_email(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'not-an-email',
            'password' => '',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }

    public function test_register_page_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_register_creates_new_user_and_redirects_to_dashboard(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('dokumen-npwp.pdf', 500, 'application/pdf');

        $response = $this->post('/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi@perusahaan.co.id',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nama_perusahaan' => 'PT Sumber Pangan Makmur',
            'nomor_npwp' => '01.234.567.8-901.000',
            'alamat' => 'Jl. Industri No. 10, Cikarang, Jawa Barat',
            'dokumen_npwp' => $file,
        ]);

        $response->assertRedirect('/dashboard');

        $this->assertDatabaseHas('tenants', [
            'company_name' => 'PT Sumber Pangan Makmur',
            'tax_number' => '01.234.567.8-901.000',
            'address' => 'Jl. Industri No. 10, Cikarang, Jawa Barat',
            'verification_status' => 'pending',
            'is_active' => true,
        ]);

        $perusahaan = PerusahaanBuyer::where('company_name', 'PT Sumber Pangan Makmur')->first();
        $this->assertNotNull($perusahaan);
        assert($perusahaan instanceof PerusahaanBuyer);
        $this->assertNotNull($perusahaan->legal_document_path);
        Storage::disk('local')->assertExists($perusahaan->legal_document_path);

        $this->assertDatabaseHas('users', [
            'name' => 'Budi Santoso',
            'email' => 'budi@perusahaan.co.id',
            'tenant_id' => $perusahaan->id,
            'is_active' => true,
        ]);

        $user = User::where('email', 'budi@perusahaan.co.id')->first();
        $this->assertNotNull($user);
        assert($user instanceof User);
        $this->assertSame($perusahaan->id, $user->tenant_id);
        $this->assertAuthenticatedAs($user);
    }

    public function test_register_validation_errors(): void
    {
        User::factory()->create(['email' => 'existing@pagora.id']);

        $response = $this->from('/register')->post('/register', [
            'name' => '',
            'email' => 'existing@pagora.id',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
            'nama_perusahaan' => '',
            'nomor_npwp' => '',
            'alamat' => '',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors([
            'name',
            'email',
            'password',
            'nama_perusahaan',
            'nomor_npwp',
            'alamat',
            'dokumen_npwp',
        ]);
        $this->assertGuest();
    }

    public function test_register_fails_with_invalid_npwp_format(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('dokumen-npwp.pdf', 500, 'application/pdf');

        $response = $this->from('/register')->post('/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi2@perusahaan.co.id',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nama_perusahaan' => 'PT Sumber Pangan Makmur',
            'nomor_npwp' => '12345-invalid',
            'alamat' => 'Jl. Industri No. 10, Cikarang',
            'dokumen_npwp' => $file,
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['nomor_npwp']);
        $this->assertGuest();
    }

    public function test_register_fails_when_legal_document_exceeds_five_megabytes(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('dokumen-npwp.pdf', 6000, 'application/pdf');

        $response = $this->from('/register')->post('/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi3@perusahaan.co.id',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nama_perusahaan' => 'PT Sumber Pangan Makmur',
            'nomor_npwp' => '01.234.567.8-901.000',
            'alamat' => 'Jl. Industri No. 10, Cikarang',
            'dokumen_npwp' => $file,
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['dokumen_npwp']);
        $this->assertGuest();
    }

    public function test_register_succeeds_with_sixteen_digit_npwp(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('dokumen-npwp.pdf', 500, 'application/pdf');

        $response = $this->post('/register', [
            'name' => 'Ahmad Dahlan',
            'email' => 'ahmad@perusahaan16.co.id',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nama_perusahaan' => 'PT Inovasi Pangan Nusantara',
            'nomor_npwp' => '0123456789010001',
            'alamat' => 'Jl. Merdeka No. 45, Jakarta Pusat',
            'dokumen_npwp' => $file,
        ]);

        $response->assertRedirect('/dashboard');

        $this->assertDatabaseHas('tenants', [
            'company_name' => 'PT Inovasi Pangan Nusantara',
            'tax_number' => '0123456789010001',
            'verification_status' => 'pending',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'ahmad@perusahaan16.co.id',
            'is_active' => true,
        ]);
    }

    public function test_google_redirect(): void
    {
        $response = $this->get('/auth/google/redirect');

        $response->assertStatus(302);
        $this->assertStringContainsString('accounts.google.com', $response->headers->get('Location') ?? '');
    }

    public function test_google_callback_links_account_and_logs_in_when_email_already_exists(): void
    {
        $user = User::factory()->create([
            'email' => 'partner@pagora.id',
            'google_id' => null,
            'is_active' => true,
        ]);

        /** @var SocialiteUserContract&MockInterface $socialiteUser */
        /** @var SocialiteUserContract&\Mockery\MockInterface $socialiteUser */
        $socialiteUser = Mockery::mock(SocialiteUserContract::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-unique-id-12345');
        $socialiteUser->shouldReceive('getEmail')->andReturn('partner@pagora.id');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/avatar.jpg');

        /** @var object&MockInterface $driver */
        /** @var object&\Mockery\MockInterface $driver */
        $driver = Mockery::mock('stdClass');
        $driver->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertSame('google-unique-id-12345', $user->google_id);
    }

    public function test_google_callback_fails_when_email_not_registered_and_no_user_created(): void
    {
        $initialUserCount = User::count();

        /** @var SocialiteUserContract&MockInterface $socialiteUser */
        /** @var SocialiteUserContract&\Mockery\MockInterface $socialiteUser */
        $socialiteUser = Mockery::mock(SocialiteUserContract::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-unregistered-99999');
        $socialiteUser->shouldReceive('getEmail')->andReturn('not_in_system@gmail.com');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/avatar.jpg');

        /** @var object&MockInterface $driver */
        /** @var object&\Mockery\MockInterface $driver */
        $driver = Mockery::mock('stdClass');
        $driver->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect();
        $response->assertSessionHasErrors(['google']);
        $this->assertGuest();
        $this->assertSame($initialUserCount, User::count());
    }

    public function test_logout_invalidates_session_and_redirects(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
