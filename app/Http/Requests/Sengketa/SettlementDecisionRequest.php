<?php

declare(strict_types=1);

namespace App\Http\Requests\Sengketa;

use App\Models\Sengketa;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Vendor accepts or refuses a settlement: reshipment, credit note, or return (UC-18 step 5, 5a).
 */
class SettlementDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Sengketa::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:accept,reject'],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'Pilih setujui atau tolak.',
            'decision.in' => 'Keputusan tidak valid.',
            'reason.required_if' => 'Alasan penolakan wajib diisi.',
            'reason.min' => 'Alasan penolakan minimal 5 karakter.',
            'reason.max' => 'Alasan penolakan maksimal 1000 karakter.',
        ];
    }

    public function isAccepted(): bool
    {
        return $this->validated('decision') === 'accept';
    }
}
