<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\OtpMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QueuedMailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Real database queue + in-memory mail transport: no SMTP traffic, but the worker path is genuine.
        config(['queue.default' => 'database']);
    }

    private function sentMessageCount(): int
    {
        /** @var ArrayTransport $transport */
        $transport = Mail::mailer()->getSymfonyTransport();

        return $transport->messages()->count();
    }

    public function test_queued_mail_waits_in_the_jobs_table_until_a_worker_processes_it(): void
    {
        Mail::to('penerima@example.test')->send(new OtpMail('123456', 'Uji', 5));

        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertSame(0, $this->sentMessageCount());

        $this->artisan('queue:work', ['--once' => true, '--tries' => 1])->assertSuccessful();

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(1, $this->sentMessageCount());
    }
}
