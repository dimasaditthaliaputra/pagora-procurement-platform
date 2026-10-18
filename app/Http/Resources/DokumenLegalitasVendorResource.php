<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DokumenLegalitasVendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DokumenLegalitasVendor
 */
class DokumenLegalitasVendorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'jenis_dokumen' => $this->jenis_dokumen,
            'nomor_dokumen' => $this->nomor_dokumen,
            'status_verifikasi' => $this->status_verifikasi,
            'alasan_penolakan' => $this->alasan_penolakan,
            'diverifikasi_pada' => $this->diverifikasi_pada?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'file_url' => route(
                'master.vendor.verifikasi-dokumen.download',
                $this->uuid
            ),
            'vendor' => $this->vendor ? [
                'uuid' => $this->vendor->uuid,
                'nama_perusahaan' => $this->vendor->nama_perusahaan,
                'email_kontak' => $this->vendor->email_kontak,
                'no_telepon' => $this->vendor->no_telepon,
                'kategori_komoditas' => $this->vendor->kategori_komoditas,
                'alamat' => $this->vendor->alamat,
                'status_verifikasi' => $this->vendor->status_verifikasi,
            ] : null,
            'verifier' => $this->verifier ? [
                'uuid' => $this->verifier->uuid,
                'nama_lengkap' => $this->verifier->nama_lengkap,
                'email' => $this->verifier->email,
            ] : null,
        ];
    }
}
