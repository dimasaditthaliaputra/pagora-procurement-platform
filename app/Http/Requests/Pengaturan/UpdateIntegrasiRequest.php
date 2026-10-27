<?php

declare(strict_types=1);

namespace App\Http\Requests\Pengaturan;

use App\Services\PengaturanService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIntegrasiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bi_aktif' => ['required', 'boolean'],
            'bi_base_url' => ['required', 'url:https', 'max:255'],
            'bi_jadwal_sinkron' => ['required', Rule::in(PengaturanService::JADWAL_SINKRON_OPTIONS)],
            'pg_provider' => ['required', Rule::in(PengaturanService::PROVIDER_OPTIONS)],
            'pg_mode' => ['required', Rule::in(PengaturanService::MODE_OPTIONS)],
            'pg_client_key' => ['nullable', 'string', 'max:255'],
            'pg_server_key' => ['nullable', 'string', 'max:255'],
            'pg_webhook_token' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom validation messages in Indonesian.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bi_aktif.required' => 'Status integrasi Bank Indonesia wajib diisi.',
            'bi_aktif.boolean' => 'Format status integrasi tidak valid.',
            'bi_base_url.required' => 'URL API Bank Indonesia wajib diisi.',
            'bi_base_url.url' => 'URL API Bank Indonesia harus berupa URL https yang valid.',
            'bi_base_url.max' => 'URL API Bank Indonesia maksimal 255 karakter.',
            'bi_jadwal_sinkron.required' => 'Jadwal sinkronisasi wajib dipilih.',
            'bi_jadwal_sinkron.in' => 'Jadwal sinkronisasi yang dipilih tidak valid.',
            'pg_provider.required' => 'Provider payment gateway wajib dipilih.',
            'pg_provider.in' => 'Provider payment gateway yang dipilih tidak valid.',
            'pg_mode.required' => 'Mode payment gateway wajib dipilih.',
            'pg_mode.in' => 'Mode payment gateway yang dipilih tidak valid.',
            'pg_client_key.max' => 'Client key maksimal 255 karakter.',
            'pg_server_key.max' => 'Server key maksimal 255 karakter.',
            'pg_webhook_token.max' => 'Token webhook maksimal 255 karakter.',
        ];
    }
}
