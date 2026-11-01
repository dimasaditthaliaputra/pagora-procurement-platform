<?php

declare(strict_types=1);

namespace App\Http\Requests\Komoditas;

use App\Models\Komoditas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexKomoditasRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Komoditas::class) ?? false;
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
            'commodity_category_uuid' => ['nullable', 'string', 'max:36'],
            'status' => ['nullable'],
            'sort' => ['nullable', 'string', Rule::in(['name', 'sku', 'unit', 'is_active', 'created_at'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
