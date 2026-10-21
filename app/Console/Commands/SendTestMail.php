<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\SmtpTestMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendTestMail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test {email : Alamat email tujuan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim email percobaan untuk memeriksa konfigurasi SMTP';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $this->info('Mailer: '.config('mail.default'));
        $this->info('Host  : '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port'));
        $this->info('From  : '.config('mail.from.address'));

        try {
            Mail::to($email)->send(new SmtpTestMail());
        } catch (Throwable $e) {
            $this->error('Gagal mengirim email: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Email percobaan terkirim ke {$email}. Cek inbox dan folder spam.");

        return self::SUCCESS;
    }
}
