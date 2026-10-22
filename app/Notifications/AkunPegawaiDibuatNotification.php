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
        public readonly string $roleLabel
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
        return (new MailMessage)
            ->subject('Aktivasi Akun Pegawai Internal PAGORA')
            ->greeting('Halo, '.$this->user->nama_lengkap.'!')
            ->line('Akun Anda untuk sistem E-Procurement PAGORA telah berhasil dibuat oleh Manajemen.')
            ->line('Berikut kredensial masuk akun Anda:')
            ->line('• Email: '.$this->user->email)
            ->line('• Role / Jabatan: '.$this->roleLabel)
            ->line('• Password Sementara: '.$this->defaultPassword)
            ->action('Masuk ke Sistem PAGORA', route('login'))
            ->line('Akun Anda belum aktif. Saat masuk pertama kali, kami akan mengirimkan kode OTP ke email ini untuk mengaktifkan akun.')
            ->line('Demi keamanan akun Anda, silakan segera ubah kata sandi default ini setelah masuk.')
            ->salutation('Salam hormat, Tim Manajemen PAGORA');
    }
}
