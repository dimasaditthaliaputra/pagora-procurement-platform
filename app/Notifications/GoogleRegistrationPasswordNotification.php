<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GoogleRegistrationPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly User $user,
        public readonly string $defaultPassword,
        public readonly string $accountType = 'Tenant'
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
        $loginUrl = route('login');

        return (new MailMessage)
            ->subject('Pendaftaran Berhasil & Kredensial Akun Google - '.config('app.name'))
            ->markdown('emails.google-registration-password', [
                'user' => $this->user,
                'accountType' => $this->accountType,
                'defaultPassword' => $this->defaultPassword,
                'loginUrl' => $loginUrl,
            ]);
    }
}
