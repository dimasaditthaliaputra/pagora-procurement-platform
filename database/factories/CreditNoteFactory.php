<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass `invoice_id` and `dispute_id` explicitly; there are no Invoice/PO factories yet.
 *
 * @extends Factory<CreditNote>
 */
class CreditNoteFactory extends Factory
{
    protected $model = CreditNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'credit_note_number' => 'CN-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'credit_date' => now()->toDateString(),
            'amount' => 100000,
            'reason' => 'Potongan tagihan atas kerusakan sebagian barang.',
            'status' => CreditNoteStatus::AwaitingVendorConfirmation,
            'created_by' => User::factory(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => CreditNoteStatus::Confirmed,
            'vendor_confirmed_at' => now(),
        ]);
    }
}
