<?php

declare(strict_types=1);

namespace App\Http\Requests\PenerimaanBarang;

use App\Models\PenerimaanBarang;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation rules for appending supplementary damage notes & photo evidence to an open QC dispute
 * (`UC-15 «extend»`, `BR-10`, `BR-17`, `RULES` §7 & §9.4).
 */
class StoreBuktiKerusakanRequest extends FormRequest
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
            'message' => ['required', 'string', 'min:5', 'max:1000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'message' => 'catatan tambahan kerusakan barang',
            'photos' => 'foto bukti tambahan kerusakan',
            'photos.*' => 'berkas foto bukti tambahan kerusakan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Catatan rincian tambahan kerusakan barang wajib diisi.',
            'message.min' => 'Catatan tambahan kerusakan minimal 5 karakter.',
            'message.max' => 'Catatan tambahan kerusakan maksimal 1.000 karakter.',
            'photos.array' => 'Format lampiran foto bukti tambahan tidak valid.',
            'photos.max' => 'Maksimal 5 foto bukti tambahan per pengiriman laporan.',
            'photos.*.file' => 'Lampiran harus berupa berkas gambar yang valid.',
            'photos.*.mimes' => 'Foto bukti tambahan harus berformat JPG, JPEG, PNG, atau WEBP.',
            'photos.*.max' => 'Ukuran setiap foto bukti tambahan maksimal 5 MB.',
        ];
    }
}
