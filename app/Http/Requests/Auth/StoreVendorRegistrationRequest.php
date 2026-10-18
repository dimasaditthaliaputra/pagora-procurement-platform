<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVendorRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Halaman registrasi vendor terbuka untuk umum (guest).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nama_perusahaan' => ['required', 'string', 'max:200'],
            'email_kontak' => [
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('vendor', 'email_kontak')->whereNull('deleted_at'),
            ],
            'no_telepon' => ['required', 'string', 'max:30'],
            'alamat' => ['required', 'string', 'max:1000'],
            'kategori_komoditas' => ['required', 'string', 'max:100'],
            'dokumen_legalitas' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:2048', // 2 MB
            ],
        ];
    }

    /**
     * Get custom messages for validator errors in Bahasa Indonesia.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama_perusahaan.required' => 'Nama perusahaan wajib diisi.',
            'nama_perusahaan.string' => 'Nama perusahaan harus berupa teks.',
            'nama_perusahaan.max' => 'Nama perusahaan tidak boleh lebih dari 200 karakter.',

            'email_kontak.required' => 'Email bisnis wajib diisi.',
            'email_kontak.email' => 'Format email bisnis tidak valid.',
            'email_kontak.max' => 'Email bisnis tidak boleh lebih dari 150 karakter.',
            'email_kontak.unique' => 'Email bisnis ini sudah terdaftar sebagai vendor.',

            'no_telepon.required' => 'Nomor telepon wajib diisi.',
            'no_telepon.string' => 'Nomor telepon harus berupa teks.',
            'no_telepon.max' => 'Nomor telepon tidak boleh lebih dari 30 karakter.',

            'alamat.required' => 'Alamat lengkap wajib diisi.',
            'alamat.string' => 'Alamat lengkap harus berupa teks.',
            'alamat.max' => 'Alamat lengkap tidak boleh lebih dari 1000 karakter.',

            'kategori_komoditas.required' => 'Kategori komoditas wajib dipilih.',
            'kategori_komoditas.string' => 'Kategori komoditas tidak valid.',
            'kategori_komoditas.max' => 'Kategori komoditas maksimal 100 karakter.',

            'dokumen_legalitas.required' => 'Dokumen legalitas (NPWP/SIUP) wajib diunggah.',
            'dokumen_legalitas.file' => 'Dokumen legalitas harus berupa file yang valid.',
            'dokumen_legalitas.mimes' => 'Format file dokumen legalitas harus berupa PDF, JPG, JPEG, atau PNG.',
            'dokumen_legalitas.max' => 'Ukuran file dokumen legalitas tidak boleh lebih dari 2 MB.',
        ];
    }
}
