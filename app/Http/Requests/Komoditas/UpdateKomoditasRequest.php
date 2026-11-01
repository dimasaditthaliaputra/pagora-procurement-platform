<?php

declare(strict_types=1);

namespace App\Http\Requests\Komoditas;

use App\Models\Komoditas;
use App\Services\KomoditasService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKomoditasRequest extends FormRequest
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
        $uuid = (string) $this->route('uuid');
        $komoditas = Komoditas::where('uuid', $uuid)->first();
        $ignoreId = $komoditas?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('commodities', 'name')
                    ->whereNull('deleted_at')
                    ->ignore($ignoreId),
            ],
            'unit' => ['required', 'string', 'max:20', Rule::in(KomoditasService::SATUAN_OPTIONS)],
            'default_shrinkage_tolerance' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                'regex:/^(?:100(?:\.0{1,2})?|\d{1,2}(?:\.\d{1,2})?)$/',
            ],
            'api_reference_code' => ['nullable', 'string', 'max:50'],
            'sku' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('commodities', 'sku')
                    ->whereNull('deleted_at')
                    ->ignore($ignoreId),
            ],
            'commodity_category_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('commodity_categories', 'uuid')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'specification' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom validation messages in Indonesian.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama komoditas wajib diisi.',
            'name.max' => 'Nama komoditas tidak boleh lebih dari 150 karakter.',
            'name.unique' => 'Nama komoditas sudah digunakan oleh data lain.',
            'unit.required' => 'Satuan komoditas wajib dipilih.',
            'unit.in' => 'Satuan yang dipilih tidak valid.',
            'default_shrinkage_tolerance.numeric' => 'Batas susut harus berupa angka.',
            'default_shrinkage_tolerance.min' => 'Batas susut minimal 0 persen.',
            'default_shrinkage_tolerance.max' => 'Batas susut maksimal 100 persen.',
            'default_shrinkage_tolerance.regex' => 'Batas susut hanya menerima format angka desimal dengan titik (contoh: 2.5).',
            'api_reference_code.max' => 'Kode referensi API maksimal 50 karakter.',
            'sku.unique' => 'SKU komoditas sudah digunakan oleh data lain.',
            'sku.max' => 'SKU tidak boleh lebih dari 50 karakter.',
            'commodity_category_uuid.exists' => 'Kategori yang dipilih tidak valid.',
            'commodity_category_uuid.uuid' => 'Kategori yang dipilih tidak valid.',
            'specification.max' => 'Spesifikasi tidak boleh lebih dari 2000 karakter.',
            'is_active.boolean' => 'Format status aktif tidak valid.',
        ];
    }
}
