<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\UndanganVendor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class UndanganVendorMail extends Mailable implements ShouldQueue
{
    public function __construct(
        public readonly UndanganVendor $invitation,
        public readonly string $inviterName,
        public readonly string $inviterRole,
        public readonly string $registrationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Undangan Kemitraan Rekanan Vendor - PAGORA',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.undangan-vendor',
            with: [
                'invitation' => $this->invitation,
                'companyName' => $this->invitation->company_name,
                'commodityCategory' => $this->invitation->kategori_komoditas,
                'notes' => $this->invitation->message,
                'inviterName' => $this->inviterName,
                'inviterRole' => $this->inviterRole,
                'registrationUrl' => $this->registrationUrl,
            ],
        );
    }
}
