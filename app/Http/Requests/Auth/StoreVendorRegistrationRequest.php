<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Helpers\NpwpHelper;
use App\Helpers\PhoneHelper;
use App\Rules\Npwp;
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
     * Normalize contact, NPWP, and email fields before validating.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => PhoneHelper::normalize($this->string('phone')->toString()),
            ]);
        }

        if ($this->has('nomor_npwp') && ! $this->filled('tax_number')) {
            $this->merge([
                'tax_number' => $this->input('nomor_npwp'),
            ]);
        }

        if ($this->has('tax_number')) {
            $this->merge([
                'tax_number' => NpwpHelper::normalize($this->input('tax_number')),
            ]);
        }

        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->string('email')->toString())),
            ]);
        }

        if ($this->has('contact_email')) {
            $this->merge([
                'contact_email' => strtolower(trim($this->string('contact_email')->toString())),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'company_name' => ['required', 'string', 'min:3', 'max:200'],
            'tax_number' => [
                'required',
                'string',
                'max:30',
                new Npwp,
                Rule::unique('vendors', 'tax_number')->whereNull('deleted_at'),
            ],
            'contact_email' => [
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('vendors', 'contact_email')->whereNull('deleted_at'),
            ],
            'phone' => ['required', 'string', 'max:30', new PhoneNumber],
            'address' => ['required', 'string', 'max:1000'],
            'otp' => ['required', 'digits:6'],
            'commodity_category_uuids' => ['required', 'array', 'min:1'],
            'commodity_category_uuids.*' => [
                'required',
                'uuid',
                Rule::exists('commodity_categories', 'uuid')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'legal_documents' => ['required', 'array', 'min:1'],
            'legal_documents.*.document_type' => ['required', 'string', Rule::in(['npwp', 'siup', 'nib'])],
            'legal_documents.*.document_number' => ['nullable', 'string', 'max:100'],
            'legal_documents.*.file' => [
                'required',
                'file',
                'mimes:pdf',
                'max:5120', // Maksimal 5 MB per file sesuai PRD NFR-05 & UC-02
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
            'name.required' => 'Nama lengkap penanggung jawab wajib diisi.',
            'name.string' => 'Nama lengkap harus berupa teks.',
            'name.min' => 'Nama lengkap minimal 3 karakter.',
            'name.max' => 'Nama lengkap tidak boleh lebih dari 255 karakter.',

            'email.required' => 'Email akun wajib diisi.',
            'email.email' => 'Format email akun tidak valid.',
            'email.max' => 'Email akun tidak boleh lebih dari 255 karakter.',
            'email.unique' => 'Email akun ini sudah terdaftar dalam sistem.',

            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal harus 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',

            'company_name.required' => 'Nama perusahaan wajib diisi.',
            'company_name.string' => 'Nama perusahaan harus berupa teks.',
            'company_name.min' => 'Nama perusahaan minimal 3 karakter.',
            'company_name.max' => 'Nama perusahaan tidak boleh lebih dari 200 karakter.',

            'tax_number.required' => 'Nomor NPWP perusahaan wajib diisi.',
            'tax_number.max' => 'Nomor NPWP tidak boleh lebih dari 30 karakter.',
            'tax_number.unique' => 'Nomor NPWP perusahaan ini sudah terdaftar.',

            'contact_email.required' => 'Email bisnis perusahaan wajib diisi.',
            'contact_email.email' => 'Format email bisnis perusahaan tidak valid.',
            'contact_email.max' => 'Email bisnis tidak boleh lebih dari 150 karakter.',
            'contact_email.unique' => 'Email bisnis ini sudah terdaftar sebagai vendor.',

            'phone.required' => 'Nomor telepon wajib diisi.',
            'phone.string' => 'Nomor telepon harus berupa teks.',
            'phone.max' => 'Nomor telepon tidak boleh lebih dari 30 karakter.',

            'address.required' => 'Alamat lengkap wajib diisi.',
            'address.string' => 'Alamat lengkap harus berupa teks.',
            'address.max' => 'Alamat lengkap tidak boleh lebih dari 1000 karakter.',

            'otp.required' => 'Kode OTP verifikasi email bisnis wajib diisi.',
            'otp.digits' => 'Kode OTP harus terdiri dari 6 digit angka.',

            'commodity_category_uuids.required' => 'Pilih minimal satu kategori komoditas.',
            'commodity_category_uuids.array' => 'Format kategori komoditas tidak valid.',
            'commodity_category_uuids.min' => 'Pilih minimal satu kategori komoditas.',
            'commodity_category_uuids.*.uuid' => 'Kategori komoditas tidak valid.',
            'commodity_category_uuids.*.exists' => 'Kategori komoditas yang dipilih tidak ditemukan atau tidak aktif.',

            'legal_documents.required' => 'Unggah minimal satu dokumen legalitas (NPWP/SIUP/NIB) berformat PDF.',
            'legal_documents.array' => 'Format daftar dokumen legalitas tidak valid.',
            'legal_documents.min' => 'Unggah minimal satu dokumen legalitas (NPWP/SIUP/NIB) berformat PDF.',
            'legal_documents.*.document_type.required' => 'Jenis dokumen legalitas wajib dipilih.',
            'legal_documents.*.document_type.in' => 'Jenis dokumen legalitas tidak valid.',
            'legal_documents.*.document_number.max' => 'Nomor dokumen tidak boleh lebih dari 100 karakter.',
            'legal_documents.*.file.required' => 'File dokumen legalitas wajib diunggah.',
            'legal_documents.*.file.file' => 'Dokumen legalitas harus berupa file yang valid.',
            'legal_documents.*.file.mimes' => 'Format file dokumen legalitas harus berupa PDF (.pdf).',
            'legal_documents.*.file.max' => 'Ukuran setiap file dokumen legalitas tidak boleh lebih dari 5 MB.',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama lengkap',
            'email' => 'email akun',
            'password' => 'kata sandi',
            'company_name' => 'nama perusahaan',
            'tax_number' => 'nomor NPWP',
            'contact_email' => 'email bisnis',
            'phone' => 'nomor telepon',
            'address' => 'alamat lengkap',
            'commodity_category_uuids' => 'kategori komoditas',
            'legal_documents' => 'dokumen legalitas',
            'legal_documents.*.file' => 'file dokumen legalitas',
            'legal_documents.*.document_type' => 'jenis dokumen legalitas',
            'legal_documents.*.document_number' => 'nomor dokumen legalitas',
        ];
    }
}
