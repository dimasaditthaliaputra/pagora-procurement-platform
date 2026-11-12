<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantRejectionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $ownerName,
        public readonly string $companyName,
        public readonly string $rejectionReason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pemberitahuan Penolakan Pendaftaran Tenant PAGORA',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.tenant-rejection',
            with: [
                'ownerName' => $this->ownerName,
                'companyName' => $this->companyName,
                'rejectionReason' => $this->rejectionReason,
            ],
        );
    }
}
