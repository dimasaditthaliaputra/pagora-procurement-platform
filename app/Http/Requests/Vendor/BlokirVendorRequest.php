<?php

declare(strict_types=1);

namespace App\Http\Requests\Vendor;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @method User|null user($guard = null)
 */
class BlokirVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user instanceof User && $user->canManageVendors();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alasan_pemblokiran' => [
                'required',
                'string',
                'min:5',
                'max:1000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'alasan_pemblokiran.required' => 'Alasan pemblokiran wajib diisi.',
            'alasan_pemblokiran.min' => 'Alasan pemblokiran minimal 5 karakter.',
            'alasan_pemblokiran.max' => 'Alasan pemblokiran maksimal 1000 karakter.',
        ];
    }
}
