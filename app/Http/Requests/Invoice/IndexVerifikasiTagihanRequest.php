<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class IndexVerifikasiTagihanRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user instanceof User && $user->can('verifyAny', Invoice::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status_matching' => ['nullable', 'string', 'in:all,pending,matched,mismatched'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'sort' => ['nullable', 'string', 'in:nomor_invoice_vendor,tanggal_invoice,jumlah_tagihan,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.max' => 'Kata kunci pencarian maksimal 100 karakter.',
            'status_matching.in' => 'Filter status pencocokan tidak valid.',
            'start_date.date' => 'Format tanggal mulai tidak valid.',
            'end_date.date' => 'Format tanggal akhir tidak valid.',
            'end_date.after_or_equal' => 'Tanggal akhir harus sama dengan atau setelah tanggal mulai.',
            'sort.in' => 'Kolom pengurutan tidak valid.',
            'direction.in' => 'Arah pengurutan harus asc atau desc.',
            'per_page.min' => 'Jumlah data per halaman minimal 5.',
            'per_page.max' => 'Jumlah data per halaman maksimal 100.',
        ];
    }
}

