<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseOrder;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        $uuid = (string) $this->route('uuid');
        /** @var PurchaseOrder|null $po */
        $po = PurchaseOrder::where('uuid', $uuid)->first();

        if (! $po) {
            return false;
        }

        return $user->can('update', $po);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vendor_uuid' => ['required', 'string', 'uuid'],
            'tanggal_po' => ['required', 'date'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.komoditas_uuid' => ['required', 'string', 'uuid', 'distinct'],
            'items.*.jumlah_pesanan' => ['required', 'numeric', 'gt:0'],
            'items.*.harga_satuan_po' => ['required', 'numeric', 'gt:0'],
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
            'vendor_uuid.required' => 'Vendor wajib dipilih.',
            'vendor_uuid.uuid' => 'Format vendor tidak valid.',
            'tanggal_po.required' => 'Tanggal PO wajib diisi.',
            'tanggal_po.date' => 'Format tanggal PO tidak valid.',
            'items.required' => 'Tambahkan minimal satu rincian komoditas.',
            'items.min' => 'Tambahkan minimal satu rincian komoditas.',
            'items.*.komoditas_uuid.required' => 'Komoditas wajib dipilih.',
            'items.*.komoditas_uuid.distinct' => 'Komoditas sudah ada di baris lain.',
            'items.*.jumlah_pesanan.required' => 'Kuantitas pesanan wajib diisi.',
            'items.*.jumlah_pesanan.gt' => 'Kuantitas harus lebih dari 0.',
            'items.*.harga_satuan_po.required' => 'Harga satuan wajib diisi.',
            'items.*.harga_satuan_po.gt' => 'Harga satuan harus lebih dari 0.',
        ];
    }
}
