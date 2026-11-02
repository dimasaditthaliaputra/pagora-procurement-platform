<?php

declare(strict_types=1);

namespace App\Http\Requests\Perusahaan;

use App\Helpers\NpwpHelper;
use App\Models\User;
use App\Rules\Npwp;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePerusahaanRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('tax_number') && $this->input('tax_number') !== null) {
            $this->merge([
                'tax_number' => NpwpHelper::normalize((string) $this->input('tax_number')),
            ]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isOwner() && $user->tenant_id !== null) {
            return $user->can('update', $user->perusahaanBuyer);
        }

        if ($user->isVendor() && $user->vendor_id !== null) {
            return $user->can('update', $user->vendor);
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        $rules = [
            'company_name' => ['required', 'string', 'min:3', 'max:200'],
            'address' => ['required', 'string', 'min:5', 'max:1000'],
        ];

        if ($user->isOwner() && $user->tenant_id !== null) {
            $rules['tax_number'] = [
                'required',
                'string',
                new Npwp,
                Rule::unique('tenants', 'tax_number')
                    ->ignore($user->tenant_id)
                    ->whereNull('deleted_at'),
            ];
        }

        if ($user->isVendor() && $user->vendor_id !== null) {
            $rules['tax_number'] = [
                'required',
                'string',
                new Npwp,
                Rule::unique('vendors', 'tax_number')
                    ->ignore($user->vendor_id)
                    ->whereNull('deleted_at'),
            ];
            $rules['contact_email'] = [
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('vendors', 'contact_email')
                    ->ignore($user->vendor_id)
                    ->whereNull('deleted_at'),
            ];
            $rules['phone'] = ['nullable', 'string', 'max:30'];
        }

        return $rules;
    }

    /**
     * Custom validation messages in Indonesian.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_name.required' => 'Nama perusahaan wajib diisi.',
            'company_name.min' => 'Nama perusahaan minimal 3 karakter.',
            'company_name.max' => 'Nama perusahaan maksimal 200 karakter.',
            'tax_number.required' => 'Nomor NPWP perusahaan wajib diisi.',
            'tax_number.unique' => 'Nomor NPWP ini sudah terdaftar oleh perusahaan lain.',
            'address.required' => 'Alamat lengkap perusahaan wajib diisi.',
            'address.min' => 'Alamat perusahaan minimal 5 karakter.',
            'address.max' => 'Alamat perusahaan maksimal 1000 karakter.',
            'contact_email.required' => 'Email kontak perusahaan wajib diisi.',
            'contact_email.email' => 'Format email kontak perusahaan tidak valid.',
            'contact_email.unique' => 'Email kontak ini sudah digunakan oleh vendor lain.',
            'phone.max' => 'Nomor telepon maksimal 30 karakter.',
        ];
    }
}
