<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendVendorOtpRequest extends FormRequest
{
    /**
     * Halaman registrasi vendor terbuka untuk umum (guest).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email_kontak' => [
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('vendor', 'email_kontak')->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email_kontak.required' => 'Email bisnis wajib diisi.',
            'email_kontak.email' => 'Format email bisnis tidak valid.',
            'email_kontak.max' => 'Email bisnis tidak boleh lebih dari 150 karakter.',
            'email_kontak.unique' => 'Email bisnis ini sudah terdaftar sebagai vendor.',
        ];
    }
}
