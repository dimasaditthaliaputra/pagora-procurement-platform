<?php

declare(strict_types=1);

namespace App\Http\Requests\Sengketa;

use App\Enums\PemicuSengketa;
use App\Enums\StatusSengketa;
use App\Models\Sengketa;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request for filtering, searching, and sorting `Log Sengketa` (`UC-18`, `FR-07`, `FR-31`, `RULES` §7 & §8.5).
 */
class IndexSengketaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Sengketa::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'string', Rule::in(['all', ...StatusSengketa::values()])],
            'trigger_type' => ['nullable', 'string', Rule::in(['all', ...PemicuSengketa::values()])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort' => ['nullable', 'string', Rule::in([
                'tanggal_dibuka',
                'status',
                'trigger_type',
                'tanggal_selesai',
                'created_at',
            ])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Custom validation messages in Bahasa Indonesia (`RULES` §7).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.max' => 'Kata kunci pencarian maksimal 150 karakter.',
            'status.in' => 'Filter status sengketa tidak valid.',
            'trigger_type.in' => 'Filter kategori pemicu sengketa tidak valid.',
            'date_from.date_format' => 'Format tanggal mulai harus YYYY-MM-DD.',
            'date_to.date_format' => 'Format tanggal akhir harus YYYY-MM-DD.',
            'date_to.after_or_equal' => 'Tanggal akhir tidak boleh lebih awal dari tanggal mulai.',
            'sort.in' => 'Kolom pengurutan sengketa tidak valid.',
            'direction.in' => 'Arah pengurutan harus asc atau desc.',
            'per_page.max' => 'Jumlah baris per halaman maksimal 100.',
        ];
    }

    /**
     * Friendly attribute names in Bahasa Indonesia (`RULES` §7).
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'search' => 'kata kunci pencarian',
            'status' => 'status sengketa',
            'trigger_type' => 'kategori pemicu sengketa',
            'date_from' => 'tanggal mulai',
            'date_to' => 'tanggal akhir',
            'sort' => 'kolom pengurutan',
            'direction' => 'arah pengurutan',
            'per_page' => 'jumlah baris per halaman',
        ];
    }
}
