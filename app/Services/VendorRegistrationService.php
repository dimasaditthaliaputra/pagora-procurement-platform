<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Vendor;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VendorRegistrationService
{
    /**
     * Pilihan kategori komoditas yang dapat disediakan oleh vendor mitra.
     *
     * @var list<string>
     */
    public const KATEGORI_OPTIONS = [
        'Agroindustri & Pangan',
        'Beras & Biji-bijian',
        'Minyak & Lemak Nabati',
        'Gula, Pemanis & Rempah',
        'Daging, Unggas & Perikanan',
        'Kimia Industri & Polimer',
        'Logam, Baja & Fabrikasi',
        'Tekstil & Kemasan',
        'Lainnya',
    ];

    public function __construct(
        private readonly VendorRepositoryInterface $vendorRepository
    ) {}

    /**
     * Registrasi data profil calon vendor baru.
     *
     * @param  array<string, mixed>  $data
     */
    public function register(array $data): Vendor
    {
        return DB::transaction(function () use ($data) {
            // Upload dokumen legalitas ke disk public (sesuai RULES.md bagian 8.7)
            if (isset($data['dokumen_legalitas']) && $data['dokumen_legalitas'] instanceof UploadedFile) {
                $file = $data['dokumen_legalitas'];
                $extension = $file->getClientOriginalExtension() ?: 'bin';
                $filename = Str::uuid()->toString().'.'.$extension;
                $path = $file->storeAs('vendors/dokumen', $filename, 'public');
                $data['dokumen_legalitas'] = $path;
            }

            // Generate slug unik berdasarkan nama perusahaan
            $baseSlug = Str::slug($data['nama_perusahaan']);
            $data['slug'] = $baseSlug.'-'.Str::lower(Str::random(6));

            // Set status verifikasi default 'pending' sesuai spesifikasi bisnis
            $data['status_verifikasi'] = 'pending';
            $data['status_aktif'] = true;

            return $this->vendorRepository->create($data);
        });
    }
}
