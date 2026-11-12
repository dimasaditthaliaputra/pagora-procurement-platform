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
            ->markdown('emails.akun-pegawai-dibuat', [
                'user' => $this->user,
                'companyName' => $companyName,
                'roleLabel' => $this->roleLabel,
                'defaultPassword' => $this->defaultPassword,
                'loginUrl' => $loginUrl,
            ]);
    }
}
