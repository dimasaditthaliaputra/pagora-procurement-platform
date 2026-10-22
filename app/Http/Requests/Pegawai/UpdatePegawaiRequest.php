<?php

declare(strict_types=1);

namespace App\Http\Requests\Pegawai;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePegawaiRequest extends FormRequest
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
        $uuid = (string) $this->route('uuid');

        return [
            'nama_lengkap' => ['required', 'string', 'min:3', 'max:150'],
            'email' => [
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($uuid, 'uuid')->whereNull('deleted_at'),
            ],
            // Which roles are actually permitted depends on the actor's role (enforced in PegawaiService::update())
            'role' => ['required', 'string', 'in:superadmin,owner,manajemen,buyer,qc_gudang,vendor'],
            'password' => ['nullable', 'string', 'min:8'],
            'status_aktif' => ['required', 'boolean'],
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
            'email.unique' => 'Email sudah terdaftar pada akun lain.',
            'role.required' => 'Role pegawai wajib dipilih.',
            'role.in' => 'Role yang dipilih tidak valid.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.',
            'status_aktif.required' => 'Status aktif akun wajib ditentukan.',
        ];
    }
}
