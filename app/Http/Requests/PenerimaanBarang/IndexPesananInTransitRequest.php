<?php

declare(strict_types=1);

namespace App\Http\Requests\PenerimaanBarang;

use App\Models\PenerimaanBarang;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query parameter validation for the full "View All" list of In-Transit Purchase Orders
 * awaiting QC Gudang inspection (`GET /inspeksi-qc/pesanan-in-transit` — `UC-15`).
 */
class IndexPesananInTransitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PenerimaanBarang::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'route' => [
                'nullable',
                'string',
                Rule::in(['all', 'Darat', 'Laut', 'Udara', 'Kereta Api']),
            ],
            'sort' => [
                'nullable',
                'string',
                Rule::in(['tanggal_po', 'created_at', 'estimated_arrival_date']),
            ],
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
            'route.in' => 'Filter jalur pengiriman tidak valid.',
            'sort.in' => 'Kolom pengurutan daftar pesanan tidak valid.',
            'direction.in' => 'Arah pengurutan harus asc atau desc.',
            'page.min' => 'Nomor halaman minimal 1.',
            'per_page.max' => 'Jumlah baris per halaman maksimal 100.',
        ];
    }
}
