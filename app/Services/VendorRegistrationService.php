<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UndanganVendor;
use App\Models\Vendor;
use App\Repositories\Contracts\DokumenLegalitasVendorRepositoryInterface;
use App\Repositories\Contracts\UndanganVendorRepositoryInterface;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
        private readonly DokumenLegalitasVendorRepositoryInterface $dokumenRepository,
        private readonly UndanganVendorRepositoryInterface $undanganRepository,
        private readonly OtpService $otpService
    ) {}

    /**
     * Find a valid, non-expired vendor invitation by its token.
     */
    public function findValidInvitationByToken(string $token): ?UndanganVendor
    {
        $invitation = $this->undanganRepository->findByToken($token);

        if ($invitation === null || ! $invitation->isValid()) {
            return null;
        }

        return $invitation;
    }

    /**
     * Email a one-time code to prove the contact email is real.
     *
     * @throws ValidationException
     */
    public function sendEmailOtp(string $email): void
    {
        $this->otpService->send(
            OtpService::PURPOSE_VENDOR_REGISTRATION,
            strtolower(trim($email)),
            'email_kontak'
        );
    }

    /**
     * Register a new vendor candidate and its legal document.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException when the email OTP is wrong or expired
     */
    public function register(array $data): Vendor
    {
        $this->otpService->verify(
            OtpService::PURPOSE_VENDOR_REGISTRATION,
            strtolower(trim((string) $data['email_kontak'])),
            (string) ($data['otp'] ?? '')
        );
        unset($data['otp']);

        $invitationToken = $data['invitation_token'] ?? null;
        unset($data['invitation_token']);

        return DB::transaction(function () use ($data, $invitationToken): Vendor {
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

            if (is_string($invitationToken) && $invitationToken !== '') {
                $invitation = $this->undanganRepository->findByToken($invitationToken);
                if ($invitation !== null && $invitation->isValid()) {
                    $this->undanganRepository->update($invitation, [
                        'status' => 'terdaftar',
                        'registered_at' => now(),
                    ]);
                }
            }

            return $vendor;
        });
    }
}
