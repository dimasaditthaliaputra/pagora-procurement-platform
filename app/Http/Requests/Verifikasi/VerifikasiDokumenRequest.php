<?php

declare(strict_types=1);

namespace App\Http\Requests\Verifikasi;

use Illuminate\Foundation\Http\FormRequest;

class VerifikasiDokumenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->isSuperadmin();
    }

    public function rules(): array
    {
        return [
            'aksi' => ['required', 'string', 'in:approve,reject'],
            'alasan_penolakan' => [
                'required_if:aksi,reject',
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'aksi.required' => 'Aksi verifikasi wajib dipilih.',
            'aksi.in' => 'Aksi verifikasi tidak valid.',
            'alasan_penolakan.required_if' =>
                'Alasan penolakan wajib diisi.',
            'alasan_penolakan.max' =>
                'Alasan penolakan maksimal 1000 karakter.',
        ];
    }
}