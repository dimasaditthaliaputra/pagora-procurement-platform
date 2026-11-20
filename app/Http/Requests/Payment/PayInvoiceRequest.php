<?php

declare(strict_types=1);

namespace App\Http\Requests\Payment;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class PayInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        // The detailed check on the invoice runs in `PaymentService`.
        return $user instanceof User && $user->can('verifyAny', Invoice::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
