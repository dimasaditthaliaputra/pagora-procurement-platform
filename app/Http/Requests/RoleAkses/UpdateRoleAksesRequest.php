<?php

declare(strict_types=1);

namespace App\Http\Requests\RoleAkses;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleAksesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Role::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'description.max' => 'Deskripsi role maksimal 500 karakter.',
            'permissions.present' => 'Daftar hak akses wajib dikirim.',
            'permissions.array' => 'Format daftar hak akses tidak valid.',
            'permissions.*.distinct' => 'Hak akses tidak boleh duplikat.',
        ];
    }
}
