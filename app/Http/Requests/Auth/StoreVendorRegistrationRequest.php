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

        if ($this->has('nomor_nib') && $this->input('nomor_nib') !== null) {
            $this->merge([
                'nomor_nib' => trim((string) $this->input('nomor_nib')),
            ]);
        }

        if ($this->has('nomor_ktp') && $this->input('nomor_ktp') !== null) {
            $this->merge([
                'nomor_ktp' => trim((string) $this->input('nomor_ktp')),
            ]);
        }

        if ($this->has('nomor_sppkp') && $this->input('nomor_sppkp') !== null) {
            $this->merge([
                'nomor_sppkp' => trim((string) $this->input('nomor_sppkp')),
            ]);
        }

        if ($this->has('nomor_akta') && $this->input('nomor_akta') !== null) {
            $this->merge([
                'nomor_akta' => trim((string) $this->input('nomor_akta')),
            ]);
        }

        if ($this->has('nomor_sk_kemenkumham') && $this->input('nomor_sk_kemenkumham') !== null) {
            $this->merge([
                'nomor_sk_kemenkumham' => trim((string) $this->input('nomor_sk_kemenkumham')),
            ]);
        }

        if ($this->has('business_entity') && filled($this->input('business_entity'))) {
            $this->merge([
                'business_entity' => strtolower(trim((string) $this->input('business_entity'))),
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

        if ($this->hasFile('file_sppkp') && ! $this->hasFile('dokumen_sppkp')) {
            $this->files->set('dokumen_sppkp', $this->file('file_sppkp'));
        }

        if ($this->hasFile('file_akta') && ! $this->hasFile('dokumen_akta')) {
            $this->files->set('dokumen_akta', $this->file('file_akta'));
        }

        if ($this->hasFile('file_sk_kemenkumham') && ! $this->hasFile('dokumen_sk_kemenkumham')) {
            $this->files->set('dokumen_sk_kemenkumham', $this->file('file_sk_kemenkumham'));
        }

        if ($this->has('pkp_status')) {
            $val = strtolower(trim((string) $this->input('pkp_status')));
            $isPkp = in_array($val, ['ppn', 'pkp', '1', 'true', 'yes', 'ya'], true);
            $this->merge(['is_pkp' => $isPkp]);
        } elseif ($this->has('is_pkp')) {
            $this->merge(['is_pkp' => filter_var($this->input('is_pkp'), FILTER_VALIDATE_BOOLEAN)]);
        }

        if ($this->has('legal_documents') && is_array($this->input('legal_documents'))) {
            $docs = $this->input('legal_documents');
            foreach ($docs as $idx => $doc) {
                if (is_array($doc) && ($doc['document_type'] ?? null) === 'npwp' && ! empty($doc['document_number'])) {
                    $docs[$idx]['document_number'] = NpwpHelper::normalize($doc['document_number']);
                } elseif (is_array($doc) && isset($doc['document_number'])) {
                    $docs[$idx]['document_number'] = trim((string) $doc['document_number']);
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
                'max:20',
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
            'phone' => ['required', 'string', 'max:20', new PhoneNumber],
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
            'business_entity' => ['nullable', 'string', Rule::in(['pt', 'cv', 'firma', 'koperasi', 'perorangan', 'ud'])],
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
            'nomor_nib' => ['nullable', 'string', 'max:20'],
            'nomor_ktp' => ['nullable', 'string', 'max:16'],
            'is_pkp' => ['nullable', 'boolean'],
            'pkp_status' => ['nullable', 'string', Rule::in(['ppn', 'non_ppn', 'pkp', 'non_pkp'])],
            'dokumen_sppkp' => ['nullable', 'file', 'mimes:pdf', 'min:1', 'max:5120'],
            'file_sppkp' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'nomor_sppkp' => ['nullable', 'string', 'max:50'],
            'dokumen_akta' => ['nullable', 'file', 'mimes:pdf', 'min:1', 'max:5120'],
            'file_akta' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'nomor_akta' => ['nullable', 'string', 'max:50'],
            'dokumen_sk_kemenkumham' => ['nullable', 'file', 'mimes:pdf', 'min:1', 'max:5120'],
            'file_sk_kemenkumham' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'nomor_sk_kemenkumham' => ['nullable', 'string', 'max:50'],
            'legal_documents' => [
                'required_without_all:dokumen_npwp,file_npwp',
                'array',
                'min:1',
                'max:10',
            ],
            'legal_documents.*.document_type' => ['required_with:legal_documents', 'string', Rule::in(['npwp', 'siup', 'nib', 'ktp', 'ktp_penanggung_jawab', 'sppkp', 'akta', 'akta_pendirian', 'sk_kemenkumham'])],
            'legal_documents.*.document_number' => ['nullable', 'string', 'max:50'],
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

            if ($this->boolean('is_pkp')) {
                $hasSppkp = $this->hasFile('dokumen_sppkp')
                    || $this->hasFile('file_sppkp')
                    || (is_array($docs) && collect($docs)->contains(function ($doc): bool {
                        return is_array($doc) && in_array($doc['document_type'] ?? null, ['sppkp', 'dokumen_sppkp'], true);
                    }));

                if (! $hasSppkp) {
                    $validator->errors()->add(
                        'dokumen_sppkp',
                        'Dokumen SPPKP (Surat Pengukuhan Pengusaha Kena Pajak) wajib diunggah untuk perusahaan berstatus PKP (PPN).'
                    );
                }
            }

            $entity = strtolower(trim((string) $this->input('business_entity', '')));
            $isIncorporated = in_array($entity, ['pt', 'cv', 'firma', 'koperasi'], true);

            if ($isIncorporated) {
                $hasAkta = $this->hasFile('dokumen_akta')
                    || $this->hasFile('file_akta')
                    || (is_array($docs) && collect($docs)->contains(function ($doc): bool {
                        return is_array($doc) && in_array($doc['document_type'] ?? null, ['akta', 'akta_pendirian'], true);
                    }));

                if (! $hasAkta) {
                    $validator->errors()->add(
                        'dokumen_akta',
                        'Dokumen Akta Pendirian wajib diunggah untuk badan usaha '.strtoupper($entity).'.'
                    );
                }
                if (! $this->filled('nomor_akta')) {
                    $validator->errors()->add(
                        'nomor_akta',
                        'Nomor Akta Pendirian wajib diisi untuk badan usaha '.strtoupper($entity).'.'
                    );
                }

                $hasSk = $this->hasFile('dokumen_sk_kemenkumham')
                    || $this->hasFile('file_sk_kemenkumham')
                    || (is_array($docs) && collect($docs)->contains(function ($doc): bool {
                        return is_array($doc) && ($doc['document_type'] ?? null) === 'sk_kemenkumham';
                    }));

                if (! $hasSk) {
                    $validator->errors()->add(
                        'dokumen_sk_kemenkumham',
                        'Dokumen SK Kemenkumham wajib diunggah untuk badan usaha '.strtoupper($entity).'.'
                    );
                }
                if (! $this->filled('nomor_sk_kemenkumham')) {
                    $validator->errors()->add(
                        'nomor_sk_kemenkumham',
                        'Nomor SK Kemenkumham wajib diisi untuk badan usaha '.strtoupper($entity).'.'
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

            'business_entity.required' => 'Bentuk badan usaha wajib dipilih.',
            'business_entity.in' => 'Bentuk badan usaha harus berupa PT, CV, Firma, Koperasi, atau Perorangan/UD.',

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

            'is_pkp.boolean' => 'Pilihan status PKP tidak valid.',
            'pkp_status.in' => 'Pilihan status PKP harus antara PPN atau Non PPN.',

            'dokumen_sppkp.required' => 'Dokumen SPPKP (Surat Pengukuhan Pengusaha Kena Pajak) wajib diunggah untuk perusahaan berstatus PKP (PPN).',
            'dokumen_sppkp.file' => 'Berkas dokumen SPPKP harus berupa file yang valid.',
            'dokumen_sppkp.mimes' => 'Format berkas dokumen SPPKP harus berupa PDF (.pdf).',
            'dokumen_sppkp.min' => 'Berkas dokumen SPPKP tidak boleh kosong (minimal 1 KB).',
            'dokumen_sppkp.max' => 'Ukuran berkas dokumen SPPKP tidak boleh lebih dari 5 MB.',

            'nomor_sppkp.string' => 'Nomor SPPKP perusahaan harus berupa teks.',
            'nomor_sppkp.max' => 'Nomor SPPKP perusahaan tidak boleh lebih dari :max karakter.',

            'dokumen_akta.file' => 'Berkas dokumen akta pendirian harus berupa file yang valid.',
            'dokumen_akta.mimes' => 'Format berkas dokumen akta pendirian harus berupa PDF (.pdf).',
            'dokumen_akta.min' => 'Berkas dokumen akta pendirian tidak boleh kosong (minimal 1 KB).',
            'dokumen_akta.max' => 'Ukuran berkas dokumen akta pendirian tidak boleh lebih dari 5 MB.',

            'nomor_akta.string' => 'Nomor akta pendirian harus berupa teks.',
            'nomor_akta.max' => 'Nomor akta pendirian tidak boleh lebih dari :max karakter.',

            'dokumen_sk_kemenkumham.file' => 'Berkas dokumen SK Kemenkumham harus berupa file yang valid.',
            'dokumen_sk_kemenkumham.mimes' => 'Format berkas dokumen SK Kemenkumham harus berupa PDF (.pdf).',
            'dokumen_sk_kemenkumham.min' => 'Berkas dokumen SK Kemenkumham tidak boleh kosong (minimal 1 KB).',
            'dokumen_sk_kemenkumham.max' => 'Ukuran berkas dokumen SK Kemenkumham tidak boleh lebih dari 5 MB.',

            'nomor_sk_kemenkumham.string' => 'Nomor SK Kemenkumham harus berupa teks.',
            'nomor_sk_kemenkumham.max' => 'Nomor SK Kemenkumham tidak boleh lebih dari :max karakter.',

            'legal_documents.required' => 'Unggah minimal satu dokumen legalitas (NPWP/SIUP/NIB) berformat PDF.',
            'legal_documents.array' => 'Format daftar dokumen legalitas tidak valid.',
            'legal_documents.min' => 'Unggah minimal satu dokumen legalitas (NPWP/SIUP/NIB) berformat PDF.',
            'legal_documents.max' => 'Maksimal 10 dokumen legalitas yang dapat diunggah.',
            'legal_documents.*.document_type.required' => 'Jenis dokumen legalitas wajib dipilih.',
            'legal_documents.*.document_type.in' => 'Jenis dokumen legalitas harus berupa NPWP, SIUP, NIB, KTP, SPPKP, Akta Pendirian, atau SK Kemenkumham.',
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
            'is_pkp' => 'status PKP perusahaan',
            'pkp_status' => 'status PKP perusahaan',
            'dokumen_sppkp' => 'dokumen SPPKP perusahaan',
            'nomor_sppkp' => 'nomor SPPKP perusahaan',
            'dokumen_akta' => 'dokumen akta pendirian',
            'nomor_akta' => 'nomor akta pendirian',
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
