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
            'contact_email' => [
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('vendors', 'contact_email')->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_email.required' => 'Email bisnis wajib diisi.',
            'contact_email.email' => 'Format email bisnis tidak valid.',
            'contact_email.max' => 'Email bisnis tidak boleh lebih dari 150 karakter.',
            'contact_email.unique' => 'Email bisnis ini sudah terdaftar sebagai vendor.',
        ];
    }
}
