<?php

declare(strict_types=1);

namespace App\Http\Requests\Pengaturan;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfilAplikasiRequest extends FormRequest
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
            'nama_aplikasi' => ['required', 'string', 'max:50'],
            'tagline' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'hapus_logo' => ['nullable', 'boolean'],
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
            'nama_aplikasi.required' => 'Nama aplikasi wajib diisi.',
            'nama_aplikasi.max' => 'Nama aplikasi maksimal 50 karakter.',
            'tagline.max' => 'Tagline maksimal 100 karakter.',
            'logo.image' => 'Logo harus berupa gambar.',
            'logo.mimes' => 'Logo harus berformat PNG, JPG, JPEG, atau WEBP.',
            'logo.max' => 'Ukuran logo maksimal 2 MB.',
            'hapus_logo.boolean' => 'Format hapus logo tidak valid.',
        ];
    }
}
