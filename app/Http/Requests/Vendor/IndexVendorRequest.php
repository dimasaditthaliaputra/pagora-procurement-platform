<?php

declare(strict_types=1);

namespace App\Http\Requests\Vendor;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @method User|null user($guard = null)
 */
class IndexVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user instanceof User;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'string', 'in:all,0,1,true,false'],
            'verification_status' => ['nullable', 'string', 'in:all,pending,approved,rejected'],
            'sort' => ['nullable', 'string', 'in:company_name,contact_email,kategori_komoditas,verification_status,is_active,created_at,blocked_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.max' => 'Pencarian maksimal 100 karakter.',
            'is_active.in' => 'Status aktif tidak valid.',
            'verification_status.in' => 'Status verifikasi tidak valid.',
            'sort.in' => 'Kolom pengurutan tidak valid.',
            'direction.in' => 'Arah pengurutan tidak valid.',
            'per_page.min' => 'Jumlah per halaman minimal 1.',
            'per_page.max' => 'Jumlah per halaman maksimal 100.',
        ];
    }
}
