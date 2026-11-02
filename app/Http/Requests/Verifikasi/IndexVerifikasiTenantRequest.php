<?php

declare(strict_types=1);

namespace App\Http\Requests\Verifikasi;

use App\Models\PerusahaanBuyer;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @method User|null user($guard = null)
 */
class IndexVerifikasiTenantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PerusahaanBuyer::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'verification_status' => [
                'nullable',
                'string',
                Rule::in(['all', 'pending', 'approved', 'rejected']),
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
