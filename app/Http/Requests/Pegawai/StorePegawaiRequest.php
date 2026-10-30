<?php

declare(strict_types=1);

namespace App\Http\Requests\Pegawai;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePegawaiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nama_lengkap' => ['required', 'string', 'min:3', 'max:150'],
            'email' => [
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            // Role authorization (which roles are allowed per creator) is enforced in PegawaiService::create()
            'role' => ['required', 'string', 'in:superadmin,owner,manajemen,buyer,qc_gudang,vendor'],
            'perusahaan_uuid' => ['nullable', 'uuid', 'exists:perusahaan_buyer,uuid'],
            'vendor_uuid' => ['nullable', 'uuid', 'exists:vendor,uuid'],
        ];
    }

    /**
     * Custom validation messages in Indonesian.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama_lengkap.required' => 'Nama lengkap pegawai wajib diisi.',
            'nama_lengkap.min' => 'Nama lengkap minimal 3 karakter.',
            'nama_lengkap.max' => 'Nama lengkap maksimal 150 karakter.',
            'email.required' => 'Email pegawai wajib diisi.',
            'email.email' => 'Format email pegawai tidak valid.',
            'email.max' => 'Email maksimal 150 karakter.',
            'email.unique' => 'Email sudah terdaftar dalam sistem.',
            'role.required' => 'Role pengguna wajib dipilih.',
            'role.in' => 'Role yang dipilih tidak valid.',
            'perusahaan_uuid.uuid' => 'Format perusahaan buyer tidak valid.',
            'perusahaan_uuid.exists' => 'Perusahaan buyer yang dipilih tidak ditemukan.',
            'vendor_uuid.uuid' => 'Format perusahaan vendor tidak valid.',
            'vendor_uuid.exists' => 'Perusahaan vendor yang dipilih tidak ditemukan.',
        ];
    }
}
