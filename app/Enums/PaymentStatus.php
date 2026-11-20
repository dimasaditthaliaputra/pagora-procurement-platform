<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Payment lifecycle (`payments.payment_status` — PRD §9.1). A `paid` payment never changes again.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Pembayaran',
            self::Paid => 'Berhasil',
            self::Failed => 'Gagal',
        };
    }
}
