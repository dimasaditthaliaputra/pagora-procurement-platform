<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Helpers\NpwpHelper;
use App\Helpers\PhoneHelper;
use App\Rules\Npwp;
use App\Rules\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

        $rawEmail = $this->has('email') && filled($this->input('email'))
            ? strtolower(trim($this->string('email')->toString()))
            : null;

        $rawContactEmail = $this->has('contact_email') && filled($this->input('contact_email'))
            ? strtolower(trim($this->string('contact_email')->toString()))
            : null;

        $resolvedEmail = $rawEmail ?? $rawContactEmail;
        $resolvedContactEmail = $rawContactEmail ?? $rawEmail;

        if ($resolvedEmail !== null) {
            $this->merge([
                'email' => $resolvedEmail,
                'contact_email' => $resolvedContactEmail,
            ]);
        }

        if ($this->hasFile('file_npwp') && ! $this->hasFile('dokumen_npwp')) {
            $this->files->set('dokumen_npwp', $this->file('file_npwp'));
        }

        if ($this->hasFile('file_nib') && ! $this->hasFile('dokumen_nib')) {
            $this->files->set('dokumen_nib', $this->file('file_nib'));
        }

        if ($this->hasFile('file_ktp') && ! $this->hasFile('dokumen_ktp')) {
            $this->files->set('dokumen_ktp', $this->file('file_ktp'));
        }

        if ($this->has('legal_documents') && is_array($this->input('legal_documents'))) {
            $docs = $this->input('legal_documents');
            foreach ($docs as $idx => $doc) {
                if (is_array($doc) && ($doc['document_type'] ?? null) === 'npwp' && ! empty($doc['document_number'])) {
                    $docs[$idx]['document_number'] = NpwpHelper::normalize($doc['document_number']);
                }
            }
            $this->merge(['legal_documents' => $docs]);
        }

        $isGoogleRegistration = $this->boolean('is_google_registration')
            || session()->has('google_user')
            || filled($this->input('google_id'));

        if ($isGoogleRegistration) {
            $googleUser = session('google_user', []);
            if (! $this->filled('name') && ! empty($googleUser['name'])) {
                $this->merge(['name' => $googleUser['name']]);
            }
            if (! $this->filled('email') && ! empty($googleUser['email'])) {
                $email = strtolower(trim((string) $googleUser['email']));
                $this->merge([
                    'email' => $email,
                    'contact_email' => $email,
                ]);
            }
            if (! $this->filled('google_id') && ! empty($googleUser['google_id'])) {
                $this->merge(['google_id' => $googleUser['google_id']]);
            }
            if (! $this->filled('avatar') && ! empty($googleUser['avatar'])) {
                $this->merge(['avatar' => $googleUser['avatar']]);
            }

            if (! $this->filled('password')) {
                $generatedPassword = 'Pagora#'.Str::random(10);
                $this->merge([
                    'password' => $generatedPassword,
                    'password_confirmation' => $generatedPassword,
                    'default_password' => $generatedPassword,
                    'is_google_registration' => true,
                ]);
            }

            if (! $this->filled('otp')) {
                $this->merge([
                    'otp' => '000000',
                    'is_google_registration' => true,
                ]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isGoogleRegistration = $this->boolean('is_google_registration')
            || session()->has('google_user')
            || filled($this->input('google_id'));

        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
                Rule::unique('vendors', 'contact_email')->whereNull('deleted_at'),
            ],
            'password' => $isGoogleRegistration
                ? ['nullable', 'string', 'min:8']
                : ['required', 'string', 'min:8', 'confirmed'],
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
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'phone' => ['required', 'string', 'max:30', new PhoneNumber],
            'address' => ['required', 'string', 'max:1000'],
            'otp' => $isGoogleRegistration ? ['nullable', 'string'] : ['required', 'digits:6'],
            'commodity_category_uuids' => ['required', 'array', 'min:1'],
            'commodity_category_uuids.*' => [
                'required',
                'uuid',
                Rule::exists('commodity_categories', 'uuid')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'dokumen_npwp' => [
                'required_without:legal_documents',
                'file',
                'mimes:pdf',
                'min:1',
                'max:5120',
            ],
            'dokumen_nib' => [
                'required_without:legal_documents',
                'file',
                'mimes:pdf',
                'min:1',
                'max:5120',
            ],
            'dokumen_ktp' => [
                'required_without:legal_documents',
                'file',
                'mimes:pdf',
                'min:1',
                'max:5120',
            ],
            'file_npwp' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'file_nib' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'file_ktp' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'nomor_nib' => ['nullable', 'string', 'max:100'],
            'nomor_ktp' => ['nullable', 'string', 'max:100'],
            'legal_documents' => [
                'required_without_all:dokumen_npwp,file_npwp',
                'array',
                'min:1',
                'max:10',
            ],
            'legal_documents.*.document_type' => ['required_with:legal_documents', 'string', Rule::in(['npwp', 'siup', 'nib', 'ktp', 'ktp_penanggung_jawab'])],
            'legal_documents.*.document_number' => ['nullable', 'string', 'max:100'],
            'legal_documents.*.file' => [
                'required_with:legal_documents',
                'file',
                'mimes:pdf',
                'min:1', // Minimal 1 KB (mencegah file 0 byte)
                'max:5120', // Maksimal 5 MB per file sesuai PRD NFR-05 & UC-02
            ],
            'invitation_token' => ['nullable', 'string', 'max:64'],
            'google_id' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'string', 'max:500'],
            'is_google_registration' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance with cross-field validation rules.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasFieldFiles = $this->hasFile('dokumen_npwp') || $this->hasFile('dokumen_nib') || $this->hasFile('dokumen_ktp');
            $docs = $this->input('legal_documents', []);

            if (! $hasFieldFiles && (! is_array($docs) || count($docs) === 0)) {
                $validator->errors()->add('dokumen_npwp', 'Dokumen NPWP Perusahaan wajib diunggah.');
                $validator->errors()->add('dokumen_nib', 'Dokumen NIB Perusahaan wajib diunggah.');
                $validator->errors()->add('dokumen_ktp', 'Dokumen KTP Penanggung Jawab wajib diunggah.');
                $validator->errors()->add('legal_documents', 'Dokumen legalitas NPWP Perusahaan wajib diunggah.');

                return;
            }

            if ($hasFieldFiles) {
                if (! $this->hasFile('dokumen_npwp')) {
                    $validator->errors()->add('dokumen_npwp', 'Dokumen NPWP Perusahaan wajib diunggah.');
                }
                if (! $this->hasFile('dokumen_nib')) {
                    $validator->errors()->add('dokumen_nib', 'Dokumen NIB Perusahaan wajib diunggah.');
                }
                if (! $this->hasFile('dokumen_ktp')) {
                    $validator->errors()->add('dokumen_ktp', 'Dokumen KTP Penanggung Jawab wajib diunggah.');
                }
                if (! $this->filled('nomor_nib')) {
                    $validator->errors()->add('nomor_nib', 'Nomor NIB perusahaan wajib diisi.');
                }
                if (! $this->filled('nomor_ktp')) {
                    $validator->errors()->add('nomor_ktp', 'Nomor KTP / NIK penanggung jawab wajib diisi.');
                }
            } elseif (is_array($docs) && count($docs) > 0) {
                $hasNpwp = collect($docs)->contains(function ($doc): bool {
                    return is_array($doc) && ($doc['document_type'] ?? null) === 'npwp';
                });

                if (! $hasNpwp) {
                    $validator->errors()->add(
                        'legal_documents',
                        'Dokumen legalitas NPWP Perusahaan wajib diunggah.'
                    );
                }
            }
        });
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

            'email.required' => 'Email bisnis wajib diisi.',
            'email.email' => 'Format email bisnis tidak valid.',
            'email.max' => 'Email bisnis tidak boleh lebih dari 255 karakter.',
            'email.unique' => 'Email bisnis ini sudah terdaftar dalam sistem.',

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

            'dokumen_npwp.required' => 'Dokumen NPWP Perusahaan wajib diunggah.',
            'dokumen_npwp.file' => 'Berkas dokumen NPWP harus berupa file yang valid.',
            'dokumen_npwp.mimes' => 'Format berkas dokumen NPWP harus berupa PDF (.pdf).',
            'dokumen_npwp.min' => 'Berkas dokumen NPWP tidak boleh kosong (minimal 1 KB).',
            'dokumen_npwp.max' => 'Ukuran berkas dokumen NPWP tidak boleh lebih dari 5 MB.',

            'dokumen_nib.required' => 'Dokumen NIB Perusahaan wajib diunggah.',
            'dokumen_nib.file' => 'Berkas dokumen NIB harus berupa file yang valid.',
            'dokumen_nib.mimes' => 'Format berkas dokumen NIB harus berupa PDF (.pdf).',
            'dokumen_nib.min' => 'Berkas dokumen NIB tidak boleh kosong (minimal 1 KB).',
            'dokumen_nib.max' => 'Ukuran berkas dokumen NIB tidak boleh lebih dari 5 MB.',

            'dokumen_ktp.required' => 'Dokumen KTP Penanggung Jawab wajib diunggah.',
            'dokumen_ktp.file' => 'Berkas dokumen KTP harus berupa file yang valid.',
            'dokumen_ktp.mimes' => 'Format berkas dokumen KTP harus berupa PDF (.pdf).',
            'dokumen_ktp.min' => 'Berkas dokumen KTP tidak boleh kosong (minimal 1 KB).',
            'dokumen_ktp.max' => 'Ukuran berkas dokumen KTP tidak boleh lebih dari 5 MB.',

            'nomor_nib.required' => 'Nomor NIB perusahaan wajib diisi.',
            'nomor_nib.string' => 'Nomor NIB perusahaan harus berupa teks.',
            'nomor_nib.max' => 'Nomor NIB perusahaan tidak boleh lebih dari :max karakter.',

            'nomor_ktp.required' => 'Nomor KTP / NIK penanggung jawab wajib diisi.',
            'nomor_ktp.string' => 'Nomor KTP / NIK penanggung jawab harus berupa teks.',
            'nomor_ktp.max' => 'Nomor KTP / NIK penanggung jawab tidak boleh lebih dari :max karakter.',

            'legal_documents.required' => 'Unggah minimal satu dokumen legalitas (NPWP/SIUP/NIB) berformat PDF.',
            'legal_documents.array' => 'Format daftar dokumen legalitas tidak valid.',
            'legal_documents.min' => 'Unggah minimal satu dokumen legalitas (NPWP/SIUP/NIB) berformat PDF.',
            'legal_documents.max' => 'Maksimal 10 dokumen legalitas yang dapat diunggah.',
            'legal_documents.*.document_type.required' => 'Jenis dokumen legalitas wajib dipilih.',
            'legal_documents.*.document_type.in' => 'Jenis dokumen legalitas harus berupa NPWP, SIUP, NIB, atau KTP.',
            'legal_documents.*.document_number.max' => 'Nomor dokumen tidak boleh lebih dari 100 karakter.',
            'legal_documents.*.file.required' => 'File dokumen legalitas wajib diunggah.',
            'legal_documents.*.file.file' => 'Dokumen legalitas harus berupa file yang valid.',
            'legal_documents.*.file.mimes' => 'Format file dokumen legalitas harus berupa PDF (.pdf).',
            'legal_documents.*.file.min' => 'File dokumen legalitas tidak boleh kosong (minimal 1 KB).',
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
            'email' => 'email bisnis',
            'password' => 'kata sandi',
            'company_name' => 'nama perusahaan',
            'tax_number' => 'nomor NPWP',
            'nomor_nib' => 'nomor NIB perusahaan',
            'nomor_ktp' => 'nomor KTP penanggung jawab',
            'contact_email' => 'email bisnis perusahaan',
            'phone' => 'nomor telepon',
            'address' => 'alamat lengkap',
            'commodity_category_uuids' => 'kategori komoditas',
            'dokumen_npwp' => 'dokumen NPWP perusahaan',
            'dokumen_nib' => 'dokumen NIB perusahaan',
            'dokumen_ktp' => 'dokumen KTP penanggung jawab',
            'legal_documents' => 'dokumen legalitas',
            'legal_documents.*.file' => 'file dokumen legalitas',
            'legal_documents.*.document_type' => 'jenis dokumen legalitas',
            'legal_documents.*.document_number' => 'nomor dokumen legalitas',
        ];
    }
}
