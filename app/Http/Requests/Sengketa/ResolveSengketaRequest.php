<?php

declare(strict_types=1);

namespace App\Http\Requests\Sengketa;

use App\Models\Sengketa;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Owner/Manajemen set a dispute to `resolved` (UC-18 step 6, BR-15).
 */
class ResolveSengketaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Sengketa::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.max' => 'Catatan verifikasi maksimal 1000 karakter.',
        ];
    }
}
