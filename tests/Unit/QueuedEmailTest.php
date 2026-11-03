<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Mail\OtpMail;
use App\Mail\SmtpTestMail;
use App\Mail\TenantApprovalMail;
use App\Mail\TenantRejectionMail;
use App\Mail\UndanganVendorMail;
use App\Mail\VendorApprovalMail;
use App\Notifications\AkunPegawaiDibuatNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QueuedEmailTest extends TestCase
{
    /**
     * @return array<string, array{class-string}>
     */
    public static function queuedClasses(): array
    {
        return [
            'otp' => [OtpMail::class],
            'vendor invitation' => [UndanganVendorMail::class],
            'vendor approval' => [VendorApprovalMail::class],
            'tenant approval' => [TenantApprovalMail::class],
            'tenant rejection' => [TenantRejectionMail::class],
            'reset password' => [ResetPasswordNotification::class],
        ];
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function synchronousClasses(): array
    {
        return [
            'smtp test (must report SMTP errors)' => [SmtpTestMail::class],
            'employee credentials (plain password must not reach the jobs table)' => [AkunPegawaiDibuatNotification::class],
        ];
    }

    #[DataProvider('queuedClasses')]
    public function test_email_is_queued(string $class): void
    {
        $this->assertTrue(is_subclass_of($class, ShouldQueue::class));
    }

    #[DataProvider('synchronousClasses')]
    public function test_email_stays_synchronous(string $class): void
    {
        $this->assertFalse(is_subclass_of($class, ShouldQueue::class));
    }
}
