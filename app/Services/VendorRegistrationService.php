<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Vendor;
use App\Repositories\Contracts\DokumenLegalitasVendorRepositoryInterface;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class VendorRegistrationService
{
    /**
     * Available commodity categories for vendor partners.
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
        private readonly VendorRepositoryInterface $vendorRepository,
        private readonly DokumenLegalitasVendorRepositoryInterface $dokumenRepository
    ) {}

    /**
     * Register a new vendor candidate and its legal document.
     *
     * @param array<string, mixed> $data
     */
    public function register(array $data): Vendor
    {
        return DB::transaction(function () use ($data): Vendor {
            $file = $data['dokumen_legalitas'] ?? null;
            $documentPath = null;

            if ($file instanceof UploadedFile) {
                $extension = strtolower($file->guessExtension() ?: 'bin');
                $filename = Str::uuid()->toString().'.'.$extension;

                $documentPath = $file->storeAs(
                    'vendors/dokumen',
                    $filename,
                    'local'
                );

                if ($documentPath === false) {
                    throw new RuntimeException(
                        'Dokumen legalitas gagal disimpan.'
                    );
                }

                $data['dokumen_legalitas'] = $documentPath;
            }

            $baseSlug = Str::slug($data['nama_perusahaan']);
            $data['slug'] = $baseSlug.'-'.Str::lower(Str::random(6));
            $data['status_verifikasi'] = 'pending';
            $data['status_aktif'] = true;

            $vendor = $this->vendorRepository->create($data);

            if ($documentPath !== null) {
                $this->dokumenRepository->create([
                    'vendor_id' => $vendor->getKey(),
                    'jenis_dokumen' => 'dokumen_legalitas',
                    'nomor_dokumen' => null,
                    'file_url' => $documentPath,
                    'status_verifikasi' => 'pending',
                    'verif_by' => null,
                    'diverifikasi_pada' => null,
                    'alasan_penolakan' => null,
                ]);
            }

            return $vendor;
        });
    }
}
