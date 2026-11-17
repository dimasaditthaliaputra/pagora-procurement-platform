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
                'required',
                'file',
                'mimes:pdf',
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
            'dokumen_npwp.required' => 'Dokumen legalitas perusahaan (NPWP/SIUP) wajib diunggah.',
            'dokumen_npwp.file' => 'Berkas dokumen legalitas harus berupa file yang valid.',
            'dokumen_npwp.mimes' => 'Format berkas dokumen legalitas harus berupa PDF (.pdf).',
            'dokumen_npwp.max' => 'Ukuran berkas dokumen legalitas tidak boleh melebihi 5 MB.',
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
            'dokumen_npwp' => 'dokumen legalitas perusahaan',
        ];
    }
}
