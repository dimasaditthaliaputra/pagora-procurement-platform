<?php

declare(strict_types=1);

namespace App\Http\Requests\Sengketa;

use App\Enums\DisputeResolutionType;
use App\Models\Sengketa;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Settlement proposed by Owner/Manajemen (UC-18 step 4–5, FR-40..42, PRD §9.4).
 * Ceilings that depend on stored data (invoice balance, rejected quantity) are checked in the Service.
 */
class ProposeResolutionRequest extends FormRequest
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
        $creditNote = DisputeResolutionType::CreditNote->value;
        $return = DisputeResolutionType::Return->value;

        return [
            'resolution_type' => ['bail', 'required', Rule::in(DisputeResolutionType::values())],
            'resolution_notes' => ['required', 'string', 'min:5', 'max:2000'],
            'document_date' => ['nullable', 'date', 'before_or_equal:today'],
            'invoice_uuid' => ["required_if:resolution_type,{$creditNote}", 'nullable', 'uuid'],
            'amount' => ["required_if:resolution_type,{$creditNote}", 'nullable', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'items' => ["required_if:resolution_type,{$return}", 'nullable', 'array', 'min:1'],
            'items.*.receipt_item_uuid' => ['required', 'uuid', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999.999'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resolution_type.required' => 'Pilih jenis penyelesaian.',
            'resolution_type.in' => 'Jenis penyelesaian tidak valid.',
            'resolution_notes.required' => 'Alasan / catatan penyelesaian wajib diisi.',
            'resolution_notes.min' => 'Alasan penyelesaian minimal 5 karakter.',
            'resolution_notes.max' => 'Alasan penyelesaian maksimal 2000 karakter.',
            'document_date.date' => 'Tanggal dokumen tidak valid.',
            'document_date.before_or_equal' => 'Tanggal dokumen tidak boleh melewati hari ini.',
            'invoice_uuid.required_if' => 'Pilih invoice yang dipotong.',
            'invoice_uuid.uuid' => 'Invoice tidak valid.',
            'amount.required_if' => 'Nominal credit note wajib diisi.',
            'amount.numeric' => 'Nominal credit note harus berupa angka.',
            'amount.gt' => 'Nominal credit note harus lebih dari 0.',
            'amount.max' => 'Nominal credit note terlalu besar.',
            'items.required_if' => 'Pilih minimal 1 item yang diretur.',
            'items.min' => 'Pilih minimal 1 item yang diretur.',
            'items.*.receipt_item_uuid.required' => 'Item retur tidak valid.',
            'items.*.receipt_item_uuid.uuid' => 'Item retur tidak valid.',
            'items.*.receipt_item_uuid.distinct' => 'Item retur tidak boleh dipilih dua kali.',
            'items.*.quantity.required' => 'Kuantitas retur wajib diisi.',
            'items.*.quantity.numeric' => 'Kuantitas retur harus berupa angka.',
            'items.*.quantity.gt' => 'Kuantitas retur harus lebih dari 0.',
            'items.*.quantity.max' => 'Kuantitas retur terlalu besar.',
            'items.*.notes.max' => 'Catatan item retur maksimal 500 karakter.',
        ];
    }
}
