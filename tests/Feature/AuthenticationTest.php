<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
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
            'status_aktif' => true,
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
            'status_aktif' => true,
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
        $response = $this->post('/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi@perusahaan.co.id',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');

        $this->assertDatabaseHas('users', [
            'nama_lengkap' => 'Budi Santoso',
            'email' => 'budi@perusahaan.co.id',
            'status_aktif' => true,
        ]);

        $user = User::where('email', 'budi@perusahaan.co.id')->first();
        $this->assertNotNull($user);
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
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertGuest();
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
            'status_aktif' => true,
        ]);

        $socialiteUser = Mockery::mock(SocialiteUserContract::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-unique-id-12345');
        $socialiteUser->shouldReceive('getEmail')->andReturn('partner@pagora.id');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/avatar.jpg');

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

        $socialiteUser = Mockery::mock(SocialiteUserContract::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-unregistered-99999');
        $socialiteUser->shouldReceive('getEmail')->andReturn('not_in_system@gmail.com');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/avatar.jpg');

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
        $user = User::factory()->create(['status_aktif' => true]);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
