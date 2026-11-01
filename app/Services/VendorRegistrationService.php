<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusUndanganVendor;
use App\Enums\VerificationStatus;
use App\Models\UndanganVendor;
use App\Models\Vendor;
use App\Repositories\Contracts\DokumenLegalitasVendorRepositoryInterface;
use App\Repositories\Contracts\UndanganVendorRepositoryInterface;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VendorRegistrationService
{
    private const DOCUMENT_DISK = 'local';

    private const DOCUMENT_DIRECTORY = 'vendors/dokumen';

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
        private readonly OtpService $otpService,
        private readonly FileStorageService $fileStorage
    ) {}

    /**
     * Find a valid, non-expired vendor invitation by its token.
     */
    public function findValidInvitationByToken(string $token): ?UndanganVendor
    {
        $invitation = $this->undanganRepository->findByToken($token);

        if ($invitation === null) {
            return null;
        }

        if ($invitation->status === StatusUndanganVendor::Pending && $invitation->isExpired()) {
            $this->undanganRepository->update($invitation, ['status' => StatusUndanganVendor::Expired]);

            return null;
        }

        return $invitation->isValid() ? $invitation : null;
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
            'contact_email'
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
            strtolower(trim((string) $data['contact_email'])),
            (string) ($data['otp'] ?? '')
        );
        unset($data['otp']);

        $invitationToken = $data['invitation_token'] ?? null;
        unset($data['invitation_token']);

        $file = $data['legal_document'] ?? null;
        unset($data['legal_document']);

        // Stored before the transaction so the file can be removed if any database write fails.
        $documentPath = $file instanceof UploadedFile
            ? $this->fileStorage->store($file, self::DOCUMENT_DIRECTORY, self::DOCUMENT_DISK, uuidName: true)
            : null;

        return $this->fileStorage->rollbackOnFailure(self::DOCUMENT_DISK, $documentPath, fn (): Vendor => DB::transaction(function () use ($data, $invitationToken, $documentPath): Vendor {
            $baseSlug = Str::slug($data['company_name']);
            $data['slug'] = $baseSlug.'-'.Str::lower(Str::random(6));
            $data['verification_status'] = VerificationStatus::Pending;
            $data['is_active'] = true;

            $vendor = $this->vendorRepository->create($data);

            if ($documentPath !== null) {
                $this->dokumenRepository->create([
                    'vendor_id' => $vendor->getKey(),
                    'document_type' => 'legal_document',
                    'document_number' => null,
                    'file_path' => $documentPath,
                    'verification_status' => VerificationStatus::Pending,
                    'verified_by' => null,
                    'verified_at' => null,
                    'rejection_reason' => null,
                ]);
            }

            if (is_string($invitationToken) && $invitationToken !== '') {
                $invitation = $this->undanganRepository->findByToken($invitationToken);
                if ($invitation !== null && $invitation->isValid()) {
                    $this->undanganRepository->update($invitation, [
                        'status' => StatusUndanganVendor::Accepted,
                        'registered_at' => now(),
                    ]);
                }
            }

            return $vendor;
        }));
    }
}
