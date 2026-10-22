<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LoginOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Mail::fake();
    }

    private function newEmployee(): User
    {
        return User::factory()->create([
            'email' => 'pegawai@pagora.id',
            'password' => Hash::make('password123'),
            'status_aktif' => false,
            'email_verified_at' => null,
        ]);
    }

    private function loginWithPassword(string $password = 'password123')
    {
        return $this->from('/login')->post('/login', [
            'email' => 'pegawai@pagora.id',
            'password' => $password,
        ]);
    }

    private function mailedCode(): string
    {
        $code = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$code) {
            $code = $mail->code;

            return $mail->hasTo('pegawai@pagora.id');
        });

        return (string) $code;
    }

    public function test_new_employee_gets_otp_instead_of_being_logged_in(): void
    {
        $this->newEmployee();

        $this->loginWithPassword()->assertRedirect('/login');

        $this->assertGuest();
        Mail::assertSent(OtpMail::class, fn (OtpMail $mail) => $mail->hasTo('pegawai@pagora.id'));
        $this->get('/login')->assertInertia(fn ($page) => $page
            ->where('otp.email', 'pe*****@pagora.id'));
    }

    public function test_wrong_password_does_not_send_otp(): void
    {
        $this->newEmployee();

        $this->loginWithPassword('salah')->assertSessionHasErrors('password');

        Mail::assertNothingSent();
    }

    public function test_correct_otp_activates_account_and_logs_in(): void
    {
        $user = $this->newEmployee();
        $this->loginWithPassword();

        $this->post('/login/otp', ['otp' => $this->mailedCode()])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertTrue($user->status_aktif);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_wrong_otp_is_rejected_and_account_stays_inactive(): void
    {
        $user = $this->newEmployee();
        $this->loginWithPassword();

        $this->from('/login')->post('/login/otp', ['otp' => '000000'])->assertSessionHasErrors('otp');

        $this->assertGuest();
        $this->assertFalse($user->fresh()->status_aktif);
    }

    public function test_otp_is_locked_after_too_many_wrong_attempts(): void
    {
        $this->newEmployee();
        $this->loginWithPassword();
        $code = $this->mailedCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, 5) as $_) {
            $this->from('/login')->post('/login/otp', ['otp' => $wrong]);
        }

        $this->from('/login')->post('/login/otp', ['otp' => $code])->assertSessionHasErrors('otp');
        $this->assertGuest();
    }

    public function test_otp_cannot_be_verified_without_a_pending_login(): void
    {
        $this->newEmployee();

        $this->from('/login')->post('/login/otp', ['otp' => '123456'])->assertSessionHasErrors('otp');

        $this->assertGuest();
    }

    public function test_otp_resend_respects_cooldown(): void
    {
        $this->newEmployee();
        $this->loginWithPassword();

        $this->from('/login')->post('/login/otp/resend')->assertSessionHasErrors('otp');

        Mail::assertSentCount(1);
    }

    public function test_cancel_clears_pending_otp_step(): void
    {
        $this->newEmployee();
        $this->loginWithPassword();

        $this->delete('/login/otp')->assertRedirect('/login');

        $this->get('/login')->assertInertia(fn ($page) => $page->where('otp', null));
    }

    public function test_deactivated_verified_account_cannot_log_in_and_gets_no_otp(): void
    {
        User::factory()->create([
            'email' => 'pegawai@pagora.id',
            'password' => Hash::make('password123'),
            'status_aktif' => false,
            'email_verified_at' => now(),
        ]);

        $this->loginWithPassword()->assertSessionHasErrors('email');

        $this->assertGuest();
        Mail::assertNothingSent();
    }
}
