<?php

declare(strict_types=1);

namespace App\Http\Requests\Komoditas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexKomoditasRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'kategori' => ['nullable', 'string', 'max:100'],
            'status_aktif' => ['nullable'],
            'sort' => ['nullable', 'string', Rule::in(['nama_komoditas', 'sku', 'kategori', 'satuan', 'status_aktif', 'created_at'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
