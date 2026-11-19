<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseOrder;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Drop an item that could not be agreed (Q-24). Purchasing-only check lives in Policy `keluarkanItem`.
 */
class KeluarkanItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PurchaseOrder::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.max' => 'Alasan maksimal 500 karakter.',
        ];
    }
}
