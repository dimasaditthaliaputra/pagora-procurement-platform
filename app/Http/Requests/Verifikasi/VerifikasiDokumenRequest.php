<?php

declare(strict_types=1);

namespace App\Http\Requests\Verifikasi;

use App\Models\DokumenLegalitasVendor;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @method User|null user($guard = null)
 */
class VerifikasiDokumenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', DokumenLegalitasVendor::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'aksi' => ['required', 'string', 'in:approve,reject'],
            'rejection_reason' => [
                'required_if:aksi,reject',
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'aksi.required' => 'Aksi verifikasi wajib dipilih.',
            'aksi.in' => 'Aksi verifikasi tidak valid.',
            'rejection_reason.required_if' => 'Alasan penolakan wajib diisi.',
            'rejection_reason.max' => 'Alasan penolakan maksimal 1000 karakter.',
        ];
    }
}
