<?php

declare(strict_types=1);

namespace App\Http\Requests\Pegawai;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class IndexPegawaiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'in:all,superadmin,owner,manajemen,purchasing,qc_gudang,vendor'],
            'perusahaan' => [
                'nullable',
                'string',
                'max:64',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $str = trim((string) $value);
                    if ($str === '' || in_array($str, ['all', 'tanpa_perusahaan'], true) || Str::isUuid($str)) {
                        return;
                    }

                    $fail('Filter perusahaan tidak valid.');
                },
            ],
            'is_active' => ['nullable', 'string', 'in:all,true,false,1,0'],
            'sort' => ['nullable', 'string', 'in:name,email,role_id,is_active,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Custom validation messages in Indonesian.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.max' => 'Kata kunci pencarian maksimal 100 karakter.',
            'role.in' => 'Filter role tidak valid.',
            'is_active.in' => 'Filter status aktif tidak valid.',
            'sort.in' => 'Kolom pengurutan tidak valid.',
            'direction.in' => 'Arah pengurutan tidak valid.',
            'per_page.integer' => 'Jumlah data per halaman harus berupa angka.',
            'per_page.min' => 'Jumlah data per halaman minimal 1.',
            'per_page.max' => 'Jumlah data per halaman maksimal 100.',
        ];
    }
}
