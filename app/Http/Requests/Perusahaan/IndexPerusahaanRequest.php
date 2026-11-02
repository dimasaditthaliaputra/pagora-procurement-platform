<?php

declare(strict_types=1);

namespace App\Http\Requests\Perusahaan;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexPerusahaanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isOwner() && $user->tenant_id !== null) {
            return $user->can('view', $user->perusahaanBuyer);
        }

        if ($user->isVendor() && $user->vendor_id !== null) {
            return $user->can('view', $user->vendor);
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
