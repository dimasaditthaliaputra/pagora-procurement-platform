<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class VendorApprovalMail extends Mailable
{
    public function __construct(
        public readonly string $vendorName,
        public readonly string $companyName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pemberitahuan Persetujuan Vendor PAGORA',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.vendor-approval',
            with: [
                'vendorName' => $this->vendorName,
                'companyName' => $this->companyName,
            ],
        );
    }
}
