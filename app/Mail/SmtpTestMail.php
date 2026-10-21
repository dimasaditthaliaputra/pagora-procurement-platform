<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SmtpTestMail extends Mailable
{
    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tes SMTP '.config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.smtp-test',
            with: [
                'appName' => config('app.name'),
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'sentAt' => now()->format('d M Y H:i:s'),
            ],
        );
    }
}
