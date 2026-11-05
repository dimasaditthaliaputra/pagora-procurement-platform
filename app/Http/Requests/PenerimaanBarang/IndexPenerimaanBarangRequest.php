<?php

declare(strict_types=1);

namespace App\Http\Requests\PenerimaanBarang;

use App\Models\PenerimaanBarang;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filter & pagination validation for QC Inspection / Goods Receipt list (`UC-15`).
 */
class IndexPenerimaanBarangRequest extends FormRequest
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
            'result' => ['nullable', 'string', Rule::in(['all', 'accepted', 'rejected'])],
            'sort' => ['nullable', 'string', Rule::in(['inspected_at', 'matched_plate_number', 'created_at'])],
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
            'result.in' => 'Filter hasil inspeksi tidak valid.',
            'sort.in' => 'Kolom pengurutan tidak valid.',
            'direction.in' => 'Arah pengurutan tidak valid.',
            'per_page.max' => 'Jumlah baris per halaman maksimal 100.',
        ];
    }
}

