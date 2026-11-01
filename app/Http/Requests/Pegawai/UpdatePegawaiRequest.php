<?php

declare(strict_types=1);

namespace App\Http\Requests\Pegawai;

use App\Models\User;
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
        return $this->user()?->can('viewAny', User::class) ?? false;
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
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'email' => [
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($uuid, 'uuid')->whereNull('deleted_at'),
            ],
            // Which roles are actually permitted depends on the actor's role (enforced in PegawaiService::update())
            'role' => ['required', 'string', 'in:superadmin,owner,manajemen,purchasing,qc_gudang,vendor'],
            'tenant_uuid' => ['nullable', 'uuid', 'exists:tenants,uuid'],
            'vendor_uuid' => ['nullable', 'uuid', 'exists:vendors,uuid'],
            'password' => ['nullable', 'string', 'min:8'],
            'is_active' => ['required', 'boolean'],
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
            'name.required' => 'Nama lengkap pegawai wajib diisi.',
            'name.min' => 'Nama lengkap minimal 3 karakter.',
            'name.max' => 'Nama lengkap maksimal 150 karakter.',
            'email.required' => 'Email pegawai wajib diisi.',
            'email.email' => 'Format email pegawai tidak valid.',
            'email.max' => 'Email maksimal 150 karakter.',
            'email.unique' => 'Email sudah terdaftar pada akun lain.',
            'role.required' => 'Role pegawai wajib dipilih.',
            'role.in' => 'Role yang dipilih tidak valid.',
            'tenant_uuid.uuid' => 'Format perusahaan buyer tidak valid.',
            'tenant_uuid.exists' => 'Perusahaan buyer yang dipilih tidak ditemukan.',
            'vendor_uuid.uuid' => 'Format perusahaan vendor tidak valid.',
            'vendor_uuid.exists' => 'Perusahaan vendor yang dipilih tidak ditemukan.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.',
            'is_active.required' => 'Status aktif akun wajib ditentukan.',
        ];
    }
}
