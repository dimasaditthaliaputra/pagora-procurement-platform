<?php

declare(strict_types=1);

namespace App\Http\Requests\Sengketa;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for recording a Management/Owner review or mediation note in `log_sengketa`
 * (`UC-18` step 3, `PRD §9.3`, `BR-17`, `RULES` §7 & §8.7).
 */
class StoreTinjauanSengketaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null || $user->isSuperadmin() || $user->tenant_id === null) {
            return false;
        }

        return ($user->isManajemen() || $user->isOwner())
            && $user->hasPermission('sengketa.review');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:5', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    /**
     * Custom validation messages in Bahasa Indonesia (`RULES` §7).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Catatan tinjauan atau mediasi sengketa wajib diisi.',
            'message.min' => 'Catatan tinjauan minimal 5 karakter.',
            'message.max' => 'Catatan tinjauan maksimal 2000 karakter.',
            'attachment.file' => 'Lampiran pendukung harus berupa berkas yang valid.',
            'attachment.mimes' => 'Lampiran bukti harus berformat JPG, JPEG, PNG, WEBP, atau PDF.',
            'attachment.max' => 'Ukuran lampiran bukti maksimal 5 MB.',
        ];
    }

    /**
     * Friendly attribute names in Bahasa Indonesia (`RULES` §7).
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'message' => 'catatan tinjauan sengketa',
            'attachment' => 'lampiran bukti',
        ];
    }
}
