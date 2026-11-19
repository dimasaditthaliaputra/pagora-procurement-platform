<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseOrder;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Unit-price offer for one PO item (Purchasing proposal or Vendor counter-offer, PRD §10).
 */
class TawarItemRequest extends FormRequest
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
            'offer_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99', 'decimal:0,2'],
            'message' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'offer_amount.required' => 'Harga satuan wajib diisi.',
            'offer_amount.numeric' => 'Harga satuan harus berupa angka.',
            'offer_amount.gt' => 'Harga satuan harus lebih dari 0.',
            'offer_amount.max' => 'Harga satuan terlalu besar.',
            'offer_amount.decimal' => 'Harga satuan maksimal 2 angka di belakang koma.',
            'message.max' => 'Catatan maksimal 500 karakter.',
        ];
    }
}
