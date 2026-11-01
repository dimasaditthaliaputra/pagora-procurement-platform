<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Rules\Npwp;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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

        if ($this->hasFile('file_npwp') && ! $this->hasFile('dokumen_npwp')) {
            $this->files->set('dokumen_npwp', $this->file('file_npwp'));
        }

        if ($this->hasFile('legal_document') && ! $this->hasFile('dokumen_npwp')) {
            $this->files->set('dokumen_npwp', $this->file('legal_document'));
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'nama_perusahaan' => ['required', 'string', 'min:3', 'max:200'],
            'nomor_npwp' => ['required', 'string', 'max:30', new Npwp],
            'alamat' => ['required', 'string', 'max:1000'],
            'dokumen_npwp' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ],
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
            'alamat.required' => 'Alamat lengkap perusahaan wajib diisi.',
            'alamat.string' => 'Alamat lengkap perusahaan harus berupa teks.',
            'alamat.max' => 'Alamat perusahaan tidak boleh lebih dari :max karakter.',
            'dokumen_npwp.required' => 'Dokumen legalitas perusahaan (NPWP/SIUP) wajib diunggah.',
            'dokumen_npwp.file' => 'Berkas dokumen legalitas harus berupa file yang valid.',
            'dokumen_npwp.mimes' => 'Format dokumen legalitas harus berupa PDF, JPG, JPEG, atau PNG.',
            'dokumen_npwp.max' => 'Ukuran berkas dokumen legalitas tidak boleh melebihi 5 MB.',
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
            'nama_perusahaan' => 'nama perusahaan',
            'nomor_npwp' => 'nomor NPWP',
            'alamat' => 'alamat lengkap perusahaan',
            'dokumen_npwp' => 'dokumen legalitas perusahaan',
        ];
    }
}
