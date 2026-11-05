<?php

declare(strict_types=1);

namespace App\Http\Requests\PenerimaanBarang;

use App\Enums\PemicuSengketa;
use App\Enums\StatusSengketa;
use App\Models\PenerimaanBarang;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filter & pagination validation for QC Damage Reports (`Laporan Kerusakan Barang` — `UC-15 «extend»`).
 */
class IndexLaporanKerusakanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PenerimaanBarang::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'trigger_type' => [
                'nullable',
                'string',
                Rule::in(['all', ...PemicuSengketa::qcInspectionValues()]),
            ],
            'dispute_status' => [
                'nullable',
                'string',
                Rule::in(['all', ...array_column(StatusSengketa::cases(), 'value')]),
            ],
            'sort' => ['nullable', 'string', Rule::in(['created_at', 'received_quantity'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.max' => 'Kata kunci pencarian maksimal 100 karakter.',
            'trigger_type.in' => 'Filter kategori kerusakan barang tidak valid.',
            'dispute_status.in' => 'Filter status sengketa tidak valid.',
            'sort.in' => 'Kolom pengurutan tidak valid.',
            'direction.in' => 'Arah pengurutan tidak valid.',
            'per_page.max' => 'Jumlah baris per halaman maksimal 100.',
        ];
    }
}
