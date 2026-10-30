<?php

declare(strict_types=1);

namespace App\Http\Requests\Vendor;

use App\Models\User;
use App\Services\VendorRegistrationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @method User|null user($guard = null)
 */
class UndangVendorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user instanceof User && $user->canManageVendors();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nama_perusahaan' => [
                'required',
                'string',
                'min:3',
                'max:200',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:150',
            ],
            'kategori_komoditas' => [
                'nullable',
                'string',
                'max:100',
                Rule::in(VendorRegistrationService::KATEGORI_OPTIONS),
            ],
            'pesan' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors in Bahasa Indonesia.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama_perusahaan.required' => 'Nama perusahaan vendor wajib diisi.',
            'nama_perusahaan.min' => 'Nama perusahaan minimal 3 karakter.',
            'nama_perusahaan.max' => 'Nama perusahaan maksimal 200 karakter.',

            'email.required' => 'Email kontak vendor wajib diisi.',
            'email.email' => 'Format email kontak tidak valid.',
            'email.max' => 'Email kontak maksimal 150 karakter.',

            'kategori_komoditas.in' => 'Kategori komoditas yang dipilih tidak valid.',
            'kategori_komoditas.max' => 'Kategori komoditas maksimal 100 karakter.',

            'pesan.max' => 'Pesan undangan maksimal 1000 karakter.',
        ];
    }
}
