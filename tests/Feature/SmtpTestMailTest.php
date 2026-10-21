<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\SmtpTestMail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SmtpTestMailTest extends TestCase
{
    public function test_command_sends_test_mail_to_given_address(): void
    {
        Mail::fake();

        $this->artisan('mail:test', ['email' => 'tujuan@example.com'])
            ->assertSuccessful();

        Mail::assertSent(SmtpTestMail::class, fn (SmtpTestMail $mail) => $mail->hasTo('tujuan@example.com'));
    }

    public function test_mailable_renders_app_name_and_mailer(): void
    {
        $html = (new SmtpTestMail())->render();

        $this->assertStringContainsString(config('app.name'), $html);
        $this->assertStringContainsString('Konfigurasi SMTP berhasil', $html);
    }
}
