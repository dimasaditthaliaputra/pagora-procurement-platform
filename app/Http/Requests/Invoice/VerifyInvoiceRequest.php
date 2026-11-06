<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class VerifyInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        // Detailed check is also executed on the model in Service / Policy
        return $user->can('verifyAny', Invoice::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'notes.max' => 'Catatan verifikasi maksimal 500 karakter.',
        ];
    }
}

