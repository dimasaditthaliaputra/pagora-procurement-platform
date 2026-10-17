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
        return $this->user() !== null;
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
            'nama_komoditas' => [
                'required',
                'string',
                'max:150',
                Rule::unique('komoditas', 'nama_komoditas')
                    ->whereNull('deleted_at')
                    ->ignore($ignoreId),
            ],
            'satuan' => ['required', 'string', 'max:20', Rule::in(KomoditasService::SATUAN_OPTIONS)],
            'kode_referensi_api' => ['nullable', 'string', 'max:50'],
            'sku' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('komoditas', 'sku')
                    ->whereNull('deleted_at')
                    ->ignore($ignoreId),
            ],
            'kategori' => ['nullable', 'string', 'max:50', Rule::in(KomoditasService::KATEGORI_OPTIONS)],
            'spesifikasi' => ['nullable', 'string', 'max:2000'],
            'status_aktif' => ['nullable', 'boolean'],
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
            'nama_komoditas.required' => 'Nama komoditas wajib diisi.',
            'nama_komoditas.max' => 'Nama komoditas tidak boleh lebih dari 150 karakter.',
            'nama_komoditas.unique' => 'Nama komoditas sudah digunakan oleh data lain.',
            'satuan.required' => 'Satuan komoditas wajib dipilih.',
            'satuan.in' => 'Satuan yang dipilih tidak valid.',
            'kode_referensi_api.max' => 'Kode referensi API maksimal 50 karakter.',
            'sku.unique' => 'SKU komoditas sudah digunakan oleh data lain.',
            'sku.max' => 'SKU tidak boleh lebih dari 50 karakter.',
            'kategori.in' => 'Kategori yang dipilih tidak valid.',
            'spesifikasi.max' => 'Spesifikasi tidak boleh lebih dari 2000 karakter.',
            'status_aktif.boolean' => 'Format status aktif tidak valid.',
        ];
    }
}
