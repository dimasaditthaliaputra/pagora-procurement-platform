<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\VerificationStatus;
use App\Mail\OtpMail;
use App\Models\PerusahaanBuyer;
use App\Models\User;
use App\Notifications\GoogleRegistrationPasswordNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
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
        /** @var Cookie $cookie */
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

    /**
     * Request an OTP for the tenant registration email and return the code.
     */
    private function requestOtp(string $email): string
    {
        Mail::fake();

        $this->postJson('/register/otp/kirim', ['email' => $email])->assertOk();

        $code = null;
        Mail::assertQueued(OtpMail::class, function (OtpMail $mail) use ($email, &$code) {
            $code = $mail->code;

            return $mail->hasTo($email);
        });

        return (string) $code;
    }

    public function test_register_page_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_tenant_otp_is_sent_to_email(): void
    {
        Mail::fake();

        $response = $this->postJson('/register/otp/kirim', [
            'email' => 'calon.tenant@perusahaan.co.id',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Kode OTP telah dikirim ke email bisnis Anda.',
            ]);

        Mail::assertQueued(OtpMail::class, function (OtpMail $mail) {
            return $mail->hasTo('calon.tenant@perusahaan.co.id');
        });
    }

    public function test_tenant_otp_cannot_be_requested_for_registered_email(): void
    {
        User::factory()->create(['email' => 'terdaftar@pagora.id']);

        $response = $this->postJson('/register/otp/kirim', [
            'email' => 'terdaftar@pagora.id',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_tenant_otp_cannot_be_resent_during_cooldown(): void
    {
        Mail::fake();

        $this->postJson('/register/otp/kirim', ['email' => 'tenant.cooldown@perusahaan.co.id'])->assertOk();

        $response = $this->postJson('/register/otp/kirim', ['email' => 'tenant.cooldown@perusahaan.co.id']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_tenant_otp_can_be_verified_successfully(): void
    {
        $code = $this->requestOtp('tenant.verif@perusahaan.co.id');

        $response = $this->postJson('/register/otp/verifikasi', [
            'email' => 'tenant.verif@perusahaan.co.id',
            'otp' => $code,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Kode OTP berhasil diverifikasi.',
            ]);
    }

    public function test_tenant_otp_verification_fails_with_invalid_otp(): void
    {
        $this->requestOtp('tenant.invalid@perusahaan.co.id');

        $response = $this->postJson('/register/otp/verifikasi', [
            'email' => 'tenant.invalid@perusahaan.co.id',
            'otp' => '999999',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['otp']);
    }

    public function test_tenant_registration_fails_with_invalid_otp(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('dokumen-npwp.pdf', 500, 'application/pdf');

        $response = $this->from('/register')->post('/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi.wrong.otp@perusahaan.co.id',
            'otp' => '123456',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nama_perusahaan' => 'PT Sumber Pangan Makmur',
            'nomor_npwp' => '01.234.567.8-901.000',
            'telepon' => '081234567890',
            'alamat' => 'Jl. Industri No. 10, Cikarang',
            'dokumen_npwp' => $file,
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['otp']);
        $this->assertGuest();
    }

    public function test_register_creates_new_user_and_redirects_to_register_success(): void
    {
        Storage::fake('local');

        $otp = $this->requestOtp('budi@perusahaan.co.id');
        $file = UploadedFile::fake()->create('dokumen-npwp.pdf', 500, 'application/pdf');

        $response = $this->post('/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi@perusahaan.co.id',
            'otp' => $otp,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nama_perusahaan' => 'PT Sumber Pangan Makmur',
            'nomor_npwp' => '01.234.567.8-901.000',
            'telepon' => '081234567890',
            'alamat' => 'Jl. Industri No. 10, Cikarang, Jawa Barat',
            'dokumen_npwp' => $file,
        ]);

        $response->assertRedirect(route('register.success'));
        $response->assertSessionHas('registered_email', 'budi@perusahaan.co.id');
        $this->assertGuest();

        $this->assertDatabaseHas('tenants', [
            'company_name' => 'PT Sumber Pangan Makmur',
            'tax_number' => '01.234.567.8-901.000',
            'phone' => '+6281234567890',
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
        $this->assertNotNull($user->email_verified_at);
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
            'telepon' => '',
            'alamat' => '',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors([
            'name',
            'email',
            'otp',
            'password',
            'nama_perusahaan',
            'nomor_npwp',
            'telepon',
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
            'telepon' => '081234567890',
            'alamat' => 'Jl. Industri No. 10, Cikarang',
            'dokumen_npwp' => $file,
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['nomor_npwp']);
        $this->assertGuest();
    }

    public function test_register_fails_with_invalid_phone_number_format(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('dokumen-npwp.pdf', 500, 'application/pdf');

        $response = $this->from('/register')->post('/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi.invalid.phone@perusahaan.co.id',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nama_perusahaan' => 'PT Sumber Pangan Makmur',
            'nomor_npwp' => '01.234.567.8-901.000',
            'telepon' => '1234', // too short (< 8 digits)
            'alamat' => 'Jl. Industri No. 10, Cikarang',
            'dokumen_npwp' => $file,
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['telepon']);
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
            'telepon' => '081234567890',
            'alamat' => 'Jl. Industri No. 10, Cikarang',
            'dokumen_npwp' => $file,
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['dokumen_npwp']);
        $this->assertGuest();
    }

    public function test_register_fails_when_legal_document_is_not_pdf(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('dokumen-npwp.png', 500, 'image/png');

        $response = $this->from('/register')->post('/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi.nonpdf@perusahaan.co.id',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nama_perusahaan' => 'PT Sumber Pangan Makmur',
            'nomor_npwp' => '01.234.567.8-901.000',
            'telepon' => '081234567890',
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
        $otp = $this->requestOtp('ahmad@perusahaan16.co.id');
        $file = UploadedFile::fake()->create('dokumen-npwp.pdf', 500, 'application/pdf');

        $response = $this->post('/register', [
            'name' => 'Ahmad Dahlan',
            'email' => 'ahmad@perusahaan16.co.id',
            'otp' => $otp,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nama_perusahaan' => 'PT Inovasi Pangan Nusantara',
            'nomor_npwp' => '0123456789010001',
            'telepon' => '081234567890',
            'alamat' => 'Jl. Merdeka No. 45, Jakarta Pusat',
            'dokumen_npwp' => $file,
        ]);

        $response->assertRedirect(route('register.success'));
        $this->assertGuest();

        $this->assertDatabaseHas('tenants', [
            'company_name' => 'PT Inovasi Pangan Nusantara',
            'tax_number' => '0123456789010001',
            'phone' => '+6281234567890',
            'verification_status' => 'pending',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'ahmad@perusahaan16.co.id',
            'is_active' => true,
        ]);
    }

    public function test_pending_tenant_cannot_login(): void
    {
        $tenant = PerusahaanBuyer::factory()->create([
            'verification_status' => VerificationStatus::Pending,
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'email' => 'pending.tenant@pagora.id',
            'password' => Hash::make('password123'),
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'pending.tenant@pagora.id',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();

        // Also test JSON endpoint returns 403
        $jsonResponse = $this->postJson('/login', [
            'email' => 'pending.tenant@pagora.id',
            'password' => 'password123',
        ]);

        $jsonResponse->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_rejected_tenant_cannot_login(): void
    {
        $tenant = PerusahaanBuyer::factory()->create([
            'verification_status' => VerificationStatus::Rejected,
            'is_active' => true,
        ]);

        User::factory()->create([
            'email' => 'rejected.tenant@pagora.id',
            'password' => Hash::make('password123'),
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'rejected.tenant@pagora.id',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();

        $jsonResponse = $this->postJson('/login', [
            'email' => 'rejected.tenant@pagora.id',
            'password' => 'password123',
        ]);

        $jsonResponse->assertStatus(403);
    }

    public function test_approved_tenant_can_login_successfully(): void
    {
        $tenant = PerusahaanBuyer::factory()->create([
            'verification_status' => VerificationStatus::Approved,
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'email' => 'approved.tenant@pagora.id',
            'password' => Hash::make('password123'),
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'approved.tenant@pagora.id',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_register_fails_when_npwp_already_registered_even_with_different_email(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('dokumen-npwp.pdf', 500, 'application/pdf');

        // Tenant 1 registered
        PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Perusahaan Pertama',
            'tax_number' => '01.234.567.8-901.000',
        ]);

        // Tenant 2 attempts to register with different email but identical NPWP
        $response = $this->from('/register')->post('/register', [
            'name' => 'Calon Owner Lain',
            'email' => 'beda.email@perusahaan.co.id',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nama_perusahaan' => 'PT Perusahaan Kedua',
            'nomor_npwp' => '01.234.567.8-901.000',
            'telepon' => '081234567890',
            'alamat' => 'Jl. Lain No. 20, Jakarta',
            'dokumen_npwp' => $file,
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors([
            'nomor_npwp' => 'Nomor NPWP ini sudah terdaftar dalam sistem.',
        ]);
        $this->assertGuest();
    }

    public function test_register_fails_when_npwp_already_registered_in_unformatted_digits(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('dokumen-npwp.pdf', 500, 'application/pdf');

        // Tenant 1 registered with formatted NPWP
        PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Perusahaan Terdaftar',
            'tax_number' => '01.234.567.8-901.000',
        ]);

        // Tenant 2 attempts to register with raw unformatted 15 digits
        $response = $this->from('/register')->post('/register', [
            'name' => 'User Kedua',
            'email' => 'user2@perusahaan.co.id',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nama_perusahaan' => 'PT Perusahaan Duplikat',
            'nomor_npwp' => '012345678901000',
            'telepon' => '081234567890',
            'alamat' => 'Jl. Terusan No. 5, Surabaya',
            'dokumen_npwp' => $file,
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors([
            'nomor_npwp' => 'Nomor NPWP ini sudah terdaftar dalam sistem.',
        ]);
        $this->assertGuest();
    }

    public function test_database_unique_index_prevents_duplicate_npwp(): void
    {
        PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Pertama',
            'tax_number' => '01.234.567.8-999.000',
        ]);

        $this->expectException(QueryException::class);

        // Direct DB insertion bypassing Form Request to test DB unique constraint
        PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Kedua',
            'tax_number' => '01.234.567.8-999.000',
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
        /** @var SocialiteUserContract&MockInterface $socialiteUser */
        $socialiteUser = Mockery::mock(SocialiteUserContract::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-unique-id-12345');
        $socialiteUser->shouldReceive('getEmail')->andReturn('partner@pagora.id');
        $socialiteUser->shouldReceive('getName')->andReturn('Partner User');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/avatar.jpg');

        /** @var object&MockInterface $driver */
        /** @var object&MockInterface $driver */
        $driver = Mockery::mock('stdClass');
        $driver->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertSame('google-unique-id-12345', $user->google_id);
    }

    public function test_google_callback_redirects_to_login_with_error_when_flow_is_login_and_user_not_found(): void
    {
        $initialUserCount = User::count();

        /** @var SocialiteUserContract&MockInterface $socialiteUser */
        $socialiteUser = Mockery::mock(SocialiteUserContract::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-unregistered-99999');
        $socialiteUser->shouldReceive('getEmail')->andReturn('not_in_system@gmail.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Unregistered User');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/avatar.jpg');

        /** @var object&MockInterface $driver */
        $driver = Mockery::mock('stdClass');
        $driver->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $response = $this->withSession(['google_auth_flow' => 'login'])->get('/auth/google/callback');

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['google']);
        $this->assertGuest();
        $this->assertSame($initialUserCount, User::count());
    }

    public function test_google_callback_redirects_to_register_tenant_when_user_not_found_on_tenant_flow(): void
    {
        /** @var SocialiteUserContract&MockInterface $socialiteUser */
        $socialiteUser = Mockery::mock(SocialiteUserContract::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-tenant-new-123');
        $socialiteUser->shouldReceive('getEmail')->andReturn('newtenant@gmail.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Calon Tenant');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/avatar.jpg');

        /** @var object&MockInterface $driver */
        $driver = Mockery::mock('stdClass');
        $driver->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $response = $this->withSession(['google_auth_flow' => 'tenant'])->get('/auth/google/callback');

        $response->assertRedirect(route('register'));
        $response->assertSessionHas('google_user');
        $this->assertSame('newtenant@gmail.com', session('google_user.email'));
    }

    public function test_google_callback_redirects_to_register_vendor_when_user_not_found_on_vendor_flow(): void
    {
        /** @var SocialiteUserContract&MockInterface $socialiteUser */
        $socialiteUser = Mockery::mock(SocialiteUserContract::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-vendor-new-456');
        $socialiteUser->shouldReceive('getEmail')->andReturn('newvendor@gmail.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Calon Vendor');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/avatar.jpg');

        /** @var object&MockInterface $driver */
        $driver = Mockery::mock('stdClass');
        $driver->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $response = $this->withSession(['google_auth_flow' => 'vendor'])->get('/auth/google/callback');

        $response->assertRedirect(route('register.vendor'));
        $response->assertSessionHas('google_user');
        $this->assertSame('newvendor@gmail.com', session('google_user.email'));
    }

    public function test_google_callback_rejects_soft_deleted_user(): void
    {
        $user = User::factory()->create([
            'email' => 'deleted_user@pagora.id',
            'deleted_at' => now(),
        ]);

        /** @var SocialiteUserContract&MockInterface $socialiteUser */
        $socialiteUser = Mockery::mock(SocialiteUserContract::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-deleted-789');
        $socialiteUser->shouldReceive('getEmail')->andReturn('deleted_user@pagora.id');
        $socialiteUser->shouldReceive('getName')->andReturn('Deleted User');
        $socialiteUser->shouldReceive('getAvatar')->andReturn(null);

        /** @var object&MockInterface $driver */
        $driver = Mockery::mock('stdClass');
        $driver->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $response = $this->get('/auth/google/callback');

        $response->assertStatus(403);
        $this->assertGuest();
    }

    public function test_register_tenant_via_google_succeeds_without_password_and_otp(): void
    {
        Storage::fake('local');
        Notification::fake();

        $file = UploadedFile::fake()->create('dokumen-npwp.pdf', 500, 'application/pdf');

        $googleSessionData = [
            'name' => 'Owner Google',
            'email' => 'owner_google@gmail.com',
            'google_id' => 'google-owner-999',
            'avatar' => 'https://lh3.googleusercontent.com/owner.jpg',
        ];

        $response = $this->withSession(['google_user' => $googleSessionData])
            ->post('/register', [
                'nama_perusahaan' => 'PT Google Solusi Tenant',
                'nomor_npwp' => '01.234.567.8-999.000',
                'telepon' => '081299998888',
                'alamat' => 'Jl. Merdeka No. 100, Jakarta Pusat',
                'dokumen_npwp' => $file,
                'is_google_registration' => true,
            ]);

        $response->assertRedirect(route('register.success'));

        $user = User::where('email', 'owner_google@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('google-owner-999', $user->google_id);
        $this->assertSame('https://lh3.googleusercontent.com/owner.jpg', $user->avatar);
        $this->assertNotNull($user->email_verified_at);

        Notification::assertSentTo(
            $user,
            GoogleRegistrationPasswordNotification::class,
            function (GoogleRegistrationPasswordNotification $notification) {
                return str_starts_with($notification->defaultPassword, 'Pagora#')
                    && str_contains(strtolower($notification->accountType), 'tenant');
            }
        );

        $this->assertFalse(session()->has('google_user'));
    }

    public function test_cancel_google_auth_clears_session_and_redirects(): void
    {
        $response = $this->withSession([
            'google_user' => ['email' => 'test@gmail.com'],
            'google_auth_flow' => 'tenant',
        ])->post('/auth/google/cancel');

        $response->assertRedirect(route('register'));
        $this->assertFalse(session()->has('google_user'));
        $this->assertFalse(session()->has('google_auth_flow'));
    }

    public function test_logout_invalidates_session_and_redirects(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
