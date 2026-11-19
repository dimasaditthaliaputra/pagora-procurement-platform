<?php

declare(strict_types=1);

namespace App\Http\Requests\AnalitikHarga;

use App\Models\PrediksiHarga;
use Illuminate\Foundation\Http\FormRequest;

class RefreshAnalitikHargaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('refresh', PrediksiHarga::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
