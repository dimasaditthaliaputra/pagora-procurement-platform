<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Helpers\PhoneHelper;
use App\Rules\PhoneNumber;
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
            'company_name' => ['required', 'string', 'max:200'],
            'contact_email' => [
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('vendors', 'contact_email')->whereNull('deleted_at'),
            ],
            'phone' => ['required', 'string', 'max:30', new PhoneNumber],
            'address' => ['required', 'string', 'max:1000'],
            'kategori_komoditas' => ['required', 'string', 'max:100'],
            'otp' => ['required', 'digits:6'],
            'legal_document' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:2048', // 2 MB
            ],
            'invitation_token' => ['nullable', 'string', 'max:64'],
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
            'company_name.required' => 'Nama perusahaan wajib diisi.',
            'company_name.string' => 'Nama perusahaan harus berupa teks.',
            'company_name.max' => 'Nama perusahaan tidak boleh lebih dari 200 karakter.',

            'contact_email.required' => 'Email bisnis wajib diisi.',
            'contact_email.email' => 'Format email bisnis tidak valid.',
            'contact_email.max' => 'Email bisnis tidak boleh lebih dari 150 karakter.',
            'contact_email.unique' => 'Email bisnis ini sudah terdaftar sebagai vendor.',

            'phone.required' => 'Nomor telepon wajib diisi.',
            'phone.string' => 'Nomor telepon harus berupa teks.',
            'phone.max' => 'Nomor telepon tidak boleh lebih dari 30 karakter.',

            'address.required' => 'Alamat lengkap wajib diisi.',
            'address.string' => 'Alamat lengkap harus berupa teks.',
            'address.max' => 'Alamat lengkap tidak boleh lebih dari 1000 karakter.',

            'kategori_komoditas.required' => 'Kategori komoditas wajib dipilih.',
            'kategori_komoditas.string' => 'Kategori komoditas tidak valid.',
            'kategori_komoditas.max' => 'Kategori komoditas maksimal 100 karakter.',

            'otp.required' => 'Kode OTP wajib diisi.',
            'otp.digits' => 'Kode OTP harus terdiri dari 6 digit angka.',

            'legal_document.required' => 'Dokumen legalitas (NPWP/SIUP) wajib diunggah.',
            'legal_document.file' => 'Dokumen legalitas harus berupa file yang valid.',
            'legal_document.mimes' => 'Format file dokumen legalitas harus berupa PDF, JPG, JPEG, atau PNG.',
            'legal_document.max' => 'Ukuran file dokumen legalitas tidak boleh lebih dari 2 MB.',
        ];
    }

    /**
     * Store the phone number in one canonical format (E.164, e.g. +6281234567890).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => PhoneHelper::normalize($this->string('phone')->toString())]);
        }
    }

    /**
     * Get custom attribute names for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'phone' => 'nomor telepon',
        ];
    }
}
