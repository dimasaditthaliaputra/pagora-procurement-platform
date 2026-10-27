<?php

declare(strict_types=1);

namespace App\Http\Requests\Profil;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Accounts created through Google sign-in have no password yet, so they may set one
     * without confirming a current password.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password_saat_ini' => $this->user()?->password !== null
                ? ['required', 'string', 'current_password']
                : ['exclude'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
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
            'password_saat_ini.required' => 'Kata sandi saat ini wajib diisi.',
            'password_saat_ini.current_password' => 'Kata sandi saat ini tidak sesuai.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
            'password.min' => 'Kata sandi baru minimal :min karakter.',
        ];
    }
}
