<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $token
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
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $expire = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Atur Ulang Kata Sandi PAGORA')
            ->greeting('Halo, '.$notifiable->nama_lengkap.'!')
            ->line('Kami menerima permintaan untuk mengatur ulang kata sandi akun PAGORA Anda.')
            ->action('Atur Ulang Kata Sandi', $url)
            ->line("Tautan ini berlaku selama {$expire} menit.")
            ->line('Jika Anda tidak merasa meminta pengaturan ulang kata sandi, abaikan email ini. Kata sandi Anda tidak akan berubah.')
            ->salutation('Salam hormat, Tim PAGORA');
    }
}
