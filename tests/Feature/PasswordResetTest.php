<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_forgot_password_page_can_be_rendered(): void
    {
        $this->get('/forgot-password')->assertOk();
    }

    public function test_verified_user_receives_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'buyer@pagora.id',
            'email_verified_at' => now(),
            'status_aktif' => true,
        ]);

        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'Buyer@Pagora.id',
        ]);

        $response->assertRedirect('/forgot-password');
        $response->assertSessionHas('success');
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_unverified_user_cannot_request_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'pegawai.baru@pagora.id',
            'email_verified_at' => null,
            'status_aktif' => false,
        ]);

        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'pegawai.baru@pagora.id',
        ]);

        $response->assertRedirect('/forgot-password');
        $response->assertSessionHasErrors(['email']);
        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_deactivated_user_cannot_request_reset_link(): void
    {
        Notification::fake();

        User::factory()->create([
            'email' => 'blokir@pagora.id',
            'email_verified_at' => now(),
            'status_aktif' => false,
        ]);

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'blokir@pagora.id'])
            ->assertSessionHasErrors(['email']);

        Notification::assertNothingSent();
    }

    public function test_unregistered_email_gets_validation_error(): void
    {
        Notification::fake();

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'tidak.ada@pagora.id'])
            ->assertSessionHasErrors(['email']);

        Notification::assertNothingSent();
    }

    public function test_reset_password_page_can_be_rendered(): void
    {
        $this->get('/reset-password/some-token?email=buyer@pagora.id')->assertOk();
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'buyer@pagora.id',
            'email_verified_at' => now(),
            'status_aktif' => true,
        ]);

        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'buyer@pagora.id',
            'password' => 'kataSandiBaru123',
            'password_confirmation' => 'kataSandiBaru123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('success');
        $this->assertTrue(Hash::check('kataSandiBaru123', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_password_is_not_reset_with_invalid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'buyer@pagora.id',
            'password' => Hash::make('passwordLama123'),
            'email_verified_at' => now(),
            'status_aktif' => true,
        ]);

        $response = $this->from('/reset-password/salah')->post('/reset-password', [
            'token' => 'token-salah',
            'email' => 'buyer@pagora.id',
            'password' => 'kataSandiBaru123',
            'password_confirmation' => 'kataSandiBaru123',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertTrue(Hash::check('passwordLama123', $user->fresh()->password));
    }

    public function test_reset_password_validates_confirmation_and_length(): void
    {
        $this->from('/reset-password/token')->post('/reset-password', [
            'token' => 'token',
            'email' => 'buyer@pagora.id',
            'password' => 'pendek',
            'password_confirmation' => 'beda',
        ])->assertSessionHasErrors(['password']);
    }

    public function test_authenticated_user_cannot_access_forgot_password(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/forgot-password')->assertRedirect();
    }
}
