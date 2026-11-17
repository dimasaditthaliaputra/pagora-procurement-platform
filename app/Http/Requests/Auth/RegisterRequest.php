<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Helpers\NpwpHelper;
use App\Helpers\PhoneHelper;
use App\Rules\Npwp;
use App\Rules\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim((string) $this->input('email'))),
            ]);
        }

        if ($this->has('nama_lengkap') && ! $this->filled('name')) {
            $this->merge([
                'name' => $this->input('nama_lengkap'),
            ]);
        }

        if ($this->has('company_name') && ! $this->filled('nama_perusahaan')) {
            $this->merge([
                'nama_perusahaan' => $this->input('company_name'),
            ]);
        }

        if ($this->has('tax_number') && ! $this->filled('nomor_npwp')) {
            $this->merge([
                'nomor_npwp' => $this->input('tax_number'),
            ]);
        }

        if ($this->has('address') && ! $this->filled('alamat')) {
            $this->merge([
                'alamat' => $this->input('address'),
            ]);
        }

        if ($this->has('phone') && ! $this->filled('telepon')) {
            $this->merge([
                'telepon' => $this->input('phone'),
            ]);
        }

        if ($this->has('nomor_telepon') && ! $this->filled('telepon')) {
            $this->merge([
                'telepon' => $this->input('nomor_telepon'),
            ]);
        }

        if ($this->has('telepon')) {
            $normalizedPhone = PhoneHelper::normalize($this->string('telepon')->toString());
            $this->merge([
                'telepon' => $normalizedPhone,
                'phone' => $normalizedPhone,
            ]);
        }

        if ($this->has('nomor_npwp')) {
            $normalizedNpwp = NpwpHelper::normalize($this->input('nomor_npwp'));
            $this->merge([
                'nomor_npwp' => $normalizedNpwp,
                'tax_number' => $normalizedNpwp,
            ]);
        }

        if ($this->hasFile('file_npwp') && ! $this->hasFile('dokumen_npwp')) {
            $this->files->set('dokumen_npwp', $this->file('file_npwp'));
        }

        if ($this->hasFile('legal_document') && ! $this->hasFile('dokumen_npwp')) {
            $this->files->set('dokumen_npwp', $this->file('legal_document'));
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
                $this->merge(['email' => strtolower(trim((string) $googleUser['email']))]);
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
     * @return array<string, ValidationRule|array<mixed>|string>
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
            ],
            'password' => $isGoogleRegistration
                ? ['nullable', 'string', 'min:8']
                : ['required', 'string', 'min:8', 'confirmed'],
            'nama_perusahaan' => ['required', 'string', 'min:3', 'max:200'],
            'nomor_npwp' => [
                'required',
                'string',
                'max:30',
                new Npwp,
                Rule::unique('tenants', 'tax_number')->whereNull('deleted_at'),
            ],
            'telepon' => ['required', 'string', 'max:30', new PhoneNumber],
            'alamat' => ['required', 'string', 'max:1000'],
            'dokumen_npwp' => [
                'nullable',
                'file',
                'mimes:pdf',
                'min:1',
                'max:5120',
            ],
            'dokumen_nib' => [
                'nullable',
                'file',
                'mimes:pdf',
                'min:1',
                'max:5120',
            ],
            'dokumen_ktp' => [
                'nullable',
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
            'legal_documents' => ['nullable', 'array', 'min:1', 'max:10'],
            'legal_documents.*.document_type' => ['required_with:legal_documents', 'string', Rule::in(['npwp', 'siup', 'nib', 'ktp', 'ktp_penanggung_jawab'])],
            'legal_documents.*.document_number' => ['nullable', 'string', 'max:100'],
            'legal_documents.*.file' => [
                'required_with:legal_documents',
                'file',
                'mimes:pdf',
                'min:1',
                'max:5120',
            ],
            'otp' => $isGoogleRegistration
                ? ['nullable', 'string']
                : ['required', 'digits:6'],
            'google_id' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'string', 'max:500'],
            'is_google_registration' => ['nullable', 'boolean'],
            'default_password' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Configure the validator instance with cross-field validation rules.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasDokumenNpwp = $this->hasFile('dokumen_npwp');
            $docs = $this->input('legal_documents', []);

            if (! $hasDokumenNpwp && (! is_array($docs) || count($docs) === 0)) {
                $validator->errors()->add(
                    'dokumen_npwp',
                    'Dokumen legalitas perusahaan (NPWP / SIUP / NIB) wajib diunggah.'
                );
                $validator->errors()->add(
                    'legal_documents',
                    'Dokumen legalitas perusahaan (NPWP / SIUP / NIB) wajib diunggah.'
                );

                return;
            }

            if (is_array($docs) && count($docs) > 0) {
                $hasNpwp = collect($docs)->contains(function ($doc): bool {
                    return is_array($doc) && ($doc['document_type'] ?? null) === 'npwp';
                });

                if (! $hasNpwp) {
                    $validator->errors()->add(
                        'legal_documents',
                        'Dokumen legalitas NPWP Perusahaan wajib diunggah (pilih salah satu jenis dokumen sebagai NPWP).'
                    );
                }
            }
        });
    }

    /**
     * Get custom validation messages in Indonesian.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.string' => 'Nama lengkap harus berupa teks.',
            'name.min' => 'Nama lengkap minimal harus :min karakter.',
            'name.max' => 'Nama lengkap maksimal :max karakter.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.max' => 'Alamat email maksimal :max karakter.',
            'email.unique' => 'Alamat email ini sudah terdaftar dalam sistem.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal harus :min karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'nama_perusahaan.required' => 'Nama perusahaan wajib diisi.',
            'nama_perusahaan.string' => 'Nama perusahaan harus berupa teks.',
            'nama_perusahaan.min' => 'Nama perusahaan minimal harus :min karakter.',
            'nama_perusahaan.max' => 'Nama perusahaan tidak boleh lebih dari :max karakter.',
            'nomor_npwp.required' => 'Nomor NPWP wajib diisi.',
            'nomor_npwp.string' => 'Nomor NPWP harus berupa teks.',
            'nomor_npwp.max' => 'Nomor NPWP tidak boleh lebih dari :max karakter.',
            'nomor_npwp.unique' => 'Nomor NPWP ini sudah terdaftar dalam sistem.',
            'telepon.required' => 'Nomor telepon perusahaan wajib diisi.',
            'telepon.string' => 'Nomor telepon perusahaan harus berupa teks.',
            'telepon.max' => 'Nomor telepon perusahaan tidak boleh lebih dari :max karakter.',
            'alamat.required' => 'Alamat lengkap perusahaan wajib diisi.',
            'alamat.string' => 'Alamat lengkap perusahaan harus berupa teks.',
            'alamat.max' => 'Alamat perusahaan tidak boleh lebih dari :max karakter.',
            'legal_documents.required' => 'Unggah minimal satu dokumen legalitas (NPWP/SIUP/NIB) berformat PDF.',
            'legal_documents.array' => 'Format daftar dokumen legalitas tidak valid.',
            'legal_documents.min' => 'Unggah minimal satu dokumen legalitas (NPWP/SIUP/NIB) berformat PDF.',
            'legal_documents.max' => 'Maksimal 10 dokumen legalitas yang dapat diunggah.',
            'legal_documents.*.document_type.required' => 'Jenis dokumen legalitas wajib dipilih.',
            'legal_documents.*.document_type.in' => 'Jenis dokumen legalitas harus berupa NPWP, SIUP, atau NIB.',
            'legal_documents.*.document_number.max' => 'Nomor dokumen tidak boleh lebih dari 100 karakter.',
            'legal_documents.*.file.required' => 'Berkas dokumen legalitas wajib diunggah.',
            'legal_documents.*.file.file' => 'Berkas dokumen legalitas harus berupa file yang valid.',
            'legal_documents.*.file.mimes' => 'Format berkas dokumen legalitas harus berupa PDF (.pdf).',
            'legal_documents.*.file.min' => 'Berkas dokumen legalitas tidak boleh kosong (minimal 1 KB).',
            'legal_documents.*.file.max' => 'Ukuran setiap berkas dokumen legalitas tidak boleh melebihi 5 MB.',
            'dokumen_npwp.required' => 'Dokumen NPWP Perusahaan wajib diunggah.',
            'dokumen_npwp.file' => 'Berkas dokumen NPWP harus berupa file yang valid.',
            'dokumen_npwp.mimes' => 'Format berkas dokumen NPWP harus berupa PDF (.pdf).',
            'dokumen_npwp.min' => 'Berkas dokumen NPWP tidak boleh kosong (minimal 1 KB).',
            'dokumen_npwp.max' => 'Ukuran berkas dokumen NPWP tidak boleh melebihi 5 MB.',
            'dokumen_nib.required' => 'Dokumen NIB Perusahaan wajib diunggah.',
            'dokumen_nib.file' => 'Berkas dokumen NIB harus berupa file yang valid.',
            'dokumen_nib.mimes' => 'Format berkas dokumen NIB harus berupa PDF (.pdf).',
            'dokumen_nib.min' => 'Berkas dokumen NIB tidak boleh kosong (minimal 1 KB).',
            'dokumen_nib.max' => 'Ukuran berkas dokumen NIB tidak boleh melebihi 5 MB.',
            'dokumen_ktp.required' => 'Dokumen KTP Penanggung Jawab wajib diunggah.',
            'dokumen_ktp.file' => 'Berkas dokumen KTP harus berupa file yang valid.',
            'dokumen_ktp.mimes' => 'Format berkas dokumen KTP harus berupa PDF (.pdf).',
            'dokumen_ktp.min' => 'Berkas dokumen KTP tidak boleh kosong (minimal 1 KB).',
            'dokumen_ktp.max' => 'Ukuran berkas dokumen KTP tidak boleh melebihi 5 MB.',
            'otp.required' => 'Kode OTP verifikasi email wajib diisi.',
            'otp.digits' => 'Kode OTP harus terdiri dari 6 digit angka.',
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
            'email' => 'alamat email',
            'password' => 'kata sandi',
            'otp' => 'kode OTP',
            'nama_perusahaan' => 'nama perusahaan',
            'nomor_npwp' => 'nomor NPWP',
            'telepon' => 'nomor telepon perusahaan',
            'alamat' => 'alamat lengkap perusahaan',
            'dokumen_npwp' => 'dokumen NPWP perusahaan',
            'dokumen_nib' => 'dokumen NIB perusahaan',
            'dokumen_ktp' => 'dokumen KTP penanggung jawab',
            'legal_documents' => 'dokumen legalitas',
            'legal_documents.*.file' => 'berkas dokumen legalitas',
            'legal_documents.*.document_type' => 'jenis dokumen legalitas',
            'legal_documents.*.document_number' => 'nomor dokumen legalitas',
        ];
    }
}
