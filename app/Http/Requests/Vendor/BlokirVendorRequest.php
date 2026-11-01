<?php

declare(strict_types=1);

namespace App\Http\Requests\Vendor;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @method User|null user($guard = null)
 */
class BlokirVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Vendor::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'block_reason' => [
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
            'block_reason.required' => 'Alasan pemblokiran wajib diisi.',
            'block_reason.min' => 'Alasan pemblokiran minimal 5 karakter.',
            'block_reason.max' => 'Alasan pemblokiran maksimal 1000 karakter.',
        ];
    }
}
