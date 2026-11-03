<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseOrder;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTawaranRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nominal_tawar' => ['required', 'numeric', 'gt:0'],
            'pesan_teks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Custom validation messages in Indonesian.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nominal_tawar.required' => 'Nominal harga penawaran wajib diisi.',
            'nominal_tawar.numeric' => 'Nominal harga penawaran harus berupa angka.',
            'nominal_tawar.gt' => 'Nominal harga penawaran harus lebih dari 0.',
            'pesan_teks.max' => 'Pesan negosiasi maksimal 1000 karakter.',
        ];
    }
}
