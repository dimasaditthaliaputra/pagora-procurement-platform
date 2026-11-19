<?php

declare(strict_types=1);

namespace App\Http\Requests\Sengketa;

use App\Models\Sengketa;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for a review/mediation note (Owner, Manajemen) or a Vendor response in `dispute_logs`
 * (`UC-18` step 3 & 5, `PRD §9.3`, `BR-17`, `RULES` §7 & §8.7). Per-dispute access is checked in the Service.
 */
class StoreTinjauanSengketaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Sengketa::class) ?? false;
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
            'request_vendor_response' => ['nullable', 'boolean'],
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
