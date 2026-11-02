<?php

declare(strict_types=1);

namespace App\Http\Requests\Verifikasi;

use App\Models\PerusahaanBuyer;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @method User|null user($guard = null)
 */
class VerifikasiTenantRequest extends FormRequest
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
            'rejection_reason.required_if' => 'Alasan penolakan wajib diisi bila pendaftaran ditolak.',
            'rejection_reason.max' => 'Alasan penolakan maksimal :max karakter.',
        ];
    }
}
