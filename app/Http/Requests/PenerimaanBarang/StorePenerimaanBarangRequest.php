<?php

declare(strict_types=1);

namespace App\Http\Requests\PenerimaanBarang;

use App\Enums\PemicuSengketa;
use App\Enums\StatusItemPenerimaan;
use App\Models\PenerimaanBarang;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation rules for recording Goods Receipt / QC Inspection & Damage Reporting (`UC-15`, `FR-24`..`FR-26`, `BR-10`).
 */
class StorePenerimaanBarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PenerimaanBarang::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'purchase_order_uuid' => ['required', 'uuid'],
            'matched_plate_number' => [
                'nullable',
                'string',
                'min:3',
                'max:20',
                'regex:/^[A-Za-z0-9\s\-]+$/',
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.po_item_uuid' => ['required', 'uuid'],
            'items.*.status' => [
                'required',
                'string',
                Rule::in(array_column(StatusItemPenerimaan::cases(), 'value')),
            ],
            'items.*.trigger_type' => [
                'nullable',
                'string',
                Rule::in(PemicuSengketa::qcInspectionValues()),
            ],
            'items.*.received_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
            'items.*.photos' => ['nullable', 'array', 'max:5'],
            'items.*.photos.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'purchase_order_uuid' => 'pesanan (Purchase Order)',
            'matched_plate_number' => 'nomor plat armada yang dicocokkan',
            'notes' => 'catatan umum pemeriksaan',
            'items' => 'daftar item pemeriksaan',
            'items.*.po_item_uuid' => 'item pesanan',
            'items.*.status' => 'opsi status pemeriksaan',
            'items.*.trigger_type' => 'kategori kerusakan / kendala barang',
            'items.*.received_quantity' => 'kuantitas fisik diterima',
            'items.*.notes' => 'catatan kerusakan / ketidaksesuaian',
            'items.*.photos' => 'foto bukti kerusakan',
            'items.*.photos.*' => 'berkas foto bukti kerusakan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'purchase_order_uuid.required' => 'Pesanan (Purchase Order) yang berstatus Dalam Pengiriman wajib dipilih.',
            'purchase_order_uuid.uuid' => 'Format identifier Purchase Order tidak valid.',
            'matched_plate_number.min' => 'Nomor plat armada minimal 3 karakter.',
            'matched_plate_number.max' => 'Nomor plat armada maksimal 20 karakter.',
            'matched_plate_number.regex' => 'Nomor plat armada hanya boleh berisi huruf, angka, spasi, atau tanda hubung.',
            'notes.max' => 'Catatan umum pemeriksaan maksimal 1.000 karakter.',
            'items.required' => 'Daftar item yang diperiksa wajib diisi.',
            'items.min' => 'Minimal terdapat 1 item pesanan yang diperiksa.',
            'items.*.po_item_uuid.required' => 'Identifier item pesanan wajib diisi.',
            'items.*.po_item_uuid.uuid' => 'Format identifier item pesanan tidak valid.',
            'items.*.status.required' => 'Status hasil pemeriksaan item (Disetujui / Ditolak) wajib dipilih.',
            'items.*.status.in' => 'Status hasil pemeriksaan item harus berupa Disetujui atau Ditolak.',
            'items.*.trigger_type.in' => 'Kategori kerusakan / kendala barang yang dipilih tidak valid.',
            'items.*.received_quantity.required' => 'Kuantitas fisik diterima wajib diisi.',
            'items.*.received_quantity.numeric' => 'Kuantitas fisik diterima harus berupa angka.',
            'items.*.received_quantity.min' => 'Kuantitas fisik diterima tidak boleh kurang dari 0.',
            'items.*.notes.max' => 'Catatan kerusakan / ketidaksesuaian item maksimal 1.000 karakter.',
            'items.*.photos.array' => 'Format lampiran foto bukti tidak valid.',
            'items.*.photos.max' => 'Maksimal 5 foto bukti kerusakan per item.',
            'items.*.photos.*.file' => 'Lampiran bukti harus berupa berkas gambar yang valid.',
            'items.*.photos.*.mimes' => 'Foto bukti kerusakan harus berformat JPG, JPEG, PNG, atau WEBP.',
            'items.*.photos.*.max' => 'Ukuran setiap foto bukti kerusakan maksimal 5 MB.',
        ];
    }
}
