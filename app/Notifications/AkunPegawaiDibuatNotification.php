<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AkunPegawaiDibuatNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly User $user,
        public readonly string $defaultPassword,
        public readonly string $roleLabel,
        public readonly ?string $companyName = null
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $companyName = $this->companyName ?: 'PAGORA';
        $loginUrl = route('login');

        return (new MailMessage)
            ->subject('Undangan Bergabung & Kredensial Akun Internal - '.$companyName)
            ->view('emails.akun-pegawai-dibuat', [
                'user' => $this->user,
                'companyName' => $companyName,
                'roleLabel' => $this->roleLabel,
                'defaultPassword' => $this->defaultPassword,
                'loginUrl' => $loginUrl,
            ])
            ->greeting('Yth. Bapak/Ibu '.$this->user->name.',')
            ->line('Anda telah didaftarkan sebagai pengguna internal pada portal E-Procurement PAGORA oleh manajemen '.$companyName.'.')
            ->line('Rincian kredensial akun masuk Anda:')
            ->line('• Perusahaan: '.$companyName)
            ->line('• Peran / Jabatan Akses: '.$this->roleLabel)
            ->line('• Alamat Email: '.$this->user->email)
            ->line('• Kata Sandi Sementara: '.$this->defaultPassword)
            ->action('Masuk ke Portal PAGORA', $loginUrl)
            ->line('Akun Anda saat ini berstatus belum aktif. Saat Anda masuk untuk pertama kali, sistem akan mengirimkan kode verifikasi OTP ke email ini untuk memvalidasi identitas.')
            ->line('Demi menjaga keamanan data perusahaan, silakan segera perbarui kata sandi sementara Anda setelah berhasil masuk.')
            ->salutation('Hormat kami, Manajemen '.$companyName.' & Tim Layanan PAGORA');
    }
}
