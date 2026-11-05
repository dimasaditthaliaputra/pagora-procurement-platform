<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoice;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return filled($this->user()?->vendor_id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $vendorId = $this->user()?->vendor_id;

        return [
            'purchase_order_uuid' => [
                'required',
                'string',
                'uuid',
            ],

            'nomor_invoice_vendor' => [
                'required',
                'string',
                'max:100',
                Rule::unique('invoice', 'nomor_invoice_vendor')
                    ->where('vendor_id', $vendorId),
            ],

            'tanggal_invoice' => [
                'required',
                'date',
            ],

            'file_invoice' => [
                'nullable',
                'file',
                'max:10240',
            ],
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
            'purchase_order_uuid.required' => 'Purchase Order wajib dipilih.',
            'purchase_order_uuid.uuid' => 'Format Purchase Order tidak valid.',

            'nomor_invoice_vendor.required' => 'Nomor invoice wajib diisi.',
            'nomor_invoice_vendor.max' => 'Nomor invoice maksimal 100 karakter.',
            'nomor_invoice_vendor.unique' => 'Nomor invoice tersebut sudah pernah digunakan.',

            'tanggal_invoice.required' => 'Tanggal invoice wajib diisi.',
            'tanggal_invoice.date' => 'Format tanggal invoice tidak valid.',

            'file_invoice.file' => 'Dokumen invoice harus berupa file yang valid.',
            'file_invoice.max' => 'Ukuran file invoice maksimal 10 MB.',
        ];
    }
}
