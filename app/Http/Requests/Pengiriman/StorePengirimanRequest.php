<?php

declare(strict_types=1);

namespace App\Http\Requests\Pengiriman;

use App\Enums\StatusPengiriman;
use App\Models\Pengiriman;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePengirimanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request (Vendor only — UC-14, FR-22).
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Pengiriman::class) ?? false;
    }

    /**
     * Prepare input values before validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('carrier_name'))) {
            $this->merge([
                'carrier_name' => trim($this->input('carrier_name')),
            ]);
        }

        if (is_string($this->input('vehicle_plate_number'))) {
            $this->merge([
                'vehicle_plate_number' => strtoupper((string) preg_replace('/\s+/', ' ', trim($this->input('vehicle_plate_number')))),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'purchase_order_uuid' => ['required', 'string', 'uuid'],
            'route' => ['required', 'string', 'max:50', Rule::in(StatusPengiriman::routes())],
            'carrier_name' => ['required', 'string', 'min:2', 'max:150'],
            'vehicle_plate_number' => ['required', 'string', 'min:3', 'max:20', 'regex:/^[A-Za-z0-9\s-]+$/'],
            'estimated_arrival_date' => ['required', 'date', 'after_or_equal:today'],
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
            'purchase_order_uuid.required' => 'Purchase Order wajib dipilih.',
            'purchase_order_uuid.uuid' => 'Format Purchase Order tidak valid.',
            'route.required' => 'Jalur pengiriman wajib dipilih.',
            'route.in' => 'Jalur pengiriman yang dipilih tidak valid.',
            'route.max' => 'Jalur pengiriman maksimal 50 karakter.',
            'carrier_name.required' => 'Nama armada atau ekspedisi wajib diisi.',
            'carrier_name.min' => 'Nama armada atau ekspedisi minimal 2 karakter.',
            'carrier_name.max' => 'Nama armada atau ekspedisi maksimal 150 karakter.',
            'vehicle_plate_number.required' => 'Nomor plat armada wajib diisi.',
            'vehicle_plate_number.min' => 'Nomor plat armada minimal 3 karakter.',
            'vehicle_plate_number.max' => 'Nomor plat armada maksimal 20 karakter.',
            'vehicle_plate_number.regex' => 'Nomor plat armada hanya boleh berisi huruf, angka, spasi, atau tanda hubung.',
            'estimated_arrival_date.required' => 'Perkiraan tanggal tiba (ETA) wajib diisi.',
            'estimated_arrival_date.date' => 'Format perkiraan tanggal tiba tidak valid.',
            'estimated_arrival_date.after_or_equal' => 'Perkiraan tanggal tiba minimal hari ini atau setelahnya.',
        ];
    }
}
