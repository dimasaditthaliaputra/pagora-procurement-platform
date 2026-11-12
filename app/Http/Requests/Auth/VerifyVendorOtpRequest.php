<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyVendorOtpRequest extends FormRequest
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
            'contact_email' => ['required', 'string', 'email', 'max:150'],
            'otp' => ['required', 'digits:6'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_email.required' => 'Email bisnis perusahaan wajib diisi.',
            'contact_email.email' => 'Format email bisnis tidak valid.',
            'contact_email.max' => 'Email bisnis tidak boleh lebih dari 150 karakter.',
            'otp.required' => 'Kode OTP verifikasi email bisnis wajib diisi.',
            'otp.digits' => 'Kode OTP harus terdiri dari 6 digit angka.',
        ];
    }
}
