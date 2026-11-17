<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\StatusUndanganVendor;
use App\Enums\VerificationStatus;
use App\Models\CommodityCategory;
use App\Models\Role;
use App\Models\UndanganVendor;
use App\Models\Vendor;
use App\Notifications\GoogleRegistrationPasswordNotification;
use App\Repositories\Contracts\DokumenLegalitasVendorRepositoryInterface;
use App\Repositories\Contracts\UndanganVendorRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class VendorRegistrationService
{
    private const DOCUMENT_DISK = 'local';

    private const DOCUMENT_DIRECTORY = 'vendors/dokumen';

    /**
     * Fallback category options when the reference table has not been seeded yet.
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
        private readonly UserRepositoryInterface $userRepository,
        private readonly OtpService $otpService,
        private readonly FileStorageService $fileStorage
    ) {}

    /**
     * Active commodity categories available for vendor registration.
     *
     * @return list<array{uuid: string, name: string}>
     */
    public function getActiveCommodityCategories(): array
    {
        $categories = CommodityCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['uuid', 'name'])
            ->map(fn (CommodityCategory $category): array => [
                'uuid' => $category->uuid,
                'name' => $category->name,
            ]);

        $others = $categories->filter(fn (array $cat): bool => in_array(strtolower(trim($cat['name'])), ['lainnya', 'lain-lain', 'other'], true));
        $regular = $categories->reject(fn (array $cat): bool => in_array(strtolower(trim($cat['name'])), ['lainnya', 'lain-lain', 'other'], true));

        return $regular->concat($others)
            ->values()
            ->all();
    }

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
     * Verify the contact email OTP without consuming it.
     *
     * @throws ValidationException
     */
    public function verifyEmailOtp(string $email, string $otp): void
    {
        $this->otpService->check(
            OtpService::PURPOSE_VENDOR_REGISTRATION,
            strtolower(trim($email)),
            $otp
        );
    }

    /**
     * Register a new vendor candidate, its user account, commodity categories, and legal document.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException when the email OTP is wrong/expired or categories are invalid
     */
    public function register(array $data): Vendor
    {
        $isGoogleRegistration = ! empty($data['is_google_registration'])
            || ! empty($data['google_id'])
            || session()->has('google_user');

        if (! $isGoogleRegistration) {
            $this->otpService->verify(
                OtpService::PURPOSE_VENDOR_REGISTRATION,
                strtolower(trim((string) $data['contact_email'])),
                (string) ($data['otp'] ?? '')
            );
        }

        /** @var list<string> $categoryUuids */
        $categoryUuids = array_values(array_unique(array_filter(
            (array) ($data['commodity_category_uuids'] ?? []),
            static fn ($uuid): bool => is_string($uuid) && $uuid !== ''
        )));

        /** @var Collection<int, CommodityCategory> $categories */
        $categories = CommodityCategory::query()
            ->whereIn('uuid', $categoryUuids)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        if ($categories->isEmpty() || $categories->count() !== count($categoryUuids)) {
            throw ValidationException::withMessages([
                'commodity_category_uuids' => 'Pilih minimal satu kategori komoditas yang valid.',
            ]);
        }

        $accountName = trim((string) $data['name']);
        $accountEmail = strtolower(trim((string) $data['email']));
        $accountPassword = (string) $data['password'];
        $invitationToken = $data['invitation_token'] ?? null;

        // Stored before the transaction so every uploaded file is removed if any database write fails.
        $storedDocuments = $this->storeLegalDocuments((array) ($data['legal_documents'] ?? []), $data);
        $storedPaths = array_column($storedDocuments, 'file_path');

        return $this->fileStorage->rollbackOnFailure(self::DOCUMENT_DISK, $storedPaths, function () use (
            $data,
            $categories,
            $accountName,
            $accountEmail,
            $accountPassword,
            $invitationToken,
            $storedDocuments,
            $isGoogleRegistration
        ): Vendor {
            return DB::transaction(function () use (
                $data,
                $categories,
                $accountName,
                $accountEmail,
                $accountPassword,
                $invitationToken,
                $storedDocuments,
                $isGoogleRegistration
            ): Vendor {
                $baseSlug = Str::slug((string) $data['company_name']);

                $vendor = $this->vendorRepository->create([
                    'company_name' => trim((string) $data['company_name']),
                    'slug' => $baseSlug.'-'.Str::lower(Str::random(6)),
                    'tax_number' => trim((string) $data['tax_number']),
                    'is_pkp' => filter_var($data['is_pkp'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'contact_email' => strtolower(trim((string) $data['contact_email'])),
                    'phone' => (string) $data['phone'],
                    'address' => trim((string) $data['address']),
                    'kategori_komoditas' => $categories->pluck('name')->implode(', '),
                    'verification_status' => VerificationStatus::Pending,
                    'is_active' => true,
                ]);

                $vendor->commodityCategories()->sync($categories->pluck('id')->all());

                $vendorRole = Role::where('name', RoleName::Vendor->value)->firstOrFail();

                $userData = [
                    'name' => $accountName,
                    'email' => $accountEmail,
                    'email_verified_at' => now(),
                    'password' => Hash::make($accountPassword),
                    'role_id' => (int) $vendorRole->getKey(),
                    'vendor_id' => (int) $vendor->getKey(),
                    'is_active' => true,
                ];

                if ($isGoogleRegistration) {
                    $googleUser = session('google_user', []);
                    $userData['google_id'] = $data['google_id'] ?? $googleUser['google_id'] ?? null;
                    $userData['avatar'] = $data['avatar'] ?? $googleUser['avatar'] ?? null;
                }

                $user = $this->userRepository->create($userData);

                foreach ($storedDocuments as $document) {
                    $this->dokumenRepository->create([
                        'vendor_id' => $vendor->getKey(),
                        'document_type' => $document['document_type'],
                        'document_number' => $document['document_number'],
                        'file_path' => $document['file_path'],
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

                if ($isGoogleRegistration) {
                    try {
                        $user->notify(new GoogleRegistrationPasswordNotification($user, $accountPassword, 'Vendor (Pemasok)'));
                    } catch (Throwable) {
                        // Jangan gagalkan registrasi jika gagal mengirim notifikasi email
                    }

                    session()->forget(['google_user', 'google_auth_flow']);
                }

                return $vendor;
            });
        });
    }

    /**
     * Store each uploaded legal document PDF before starting the database transaction.
     *
     * @param  array<int, array<string, mixed>>  $documents
     * @param  array<string, mixed>  $data
     * @return list<array{document_type: string, document_number: ?string, file_path: string}>
     */
    private function storeLegalDocuments(array $documents, array $data = []): array
    {
        $storedDocuments = [];
        $timestamp = now()->timestamp;

        $fieldMap = [
            'npwp' => $data['dokumen_npwp'] ?? $data['file_npwp'] ?? null,
            'nib' => $data['dokumen_nib'] ?? $data['file_nib'] ?? null,
            'ktp' => $data['dokumen_ktp'] ?? $data['file_ktp'] ?? null,
            'sppkp' => $data['dokumen_sppkp'] ?? $data['file_sppkp'] ?? null,
            'akta' => $data['dokumen_akta'] ?? $data['file_akta'] ?? null,
        ];

        try {
            foreach ($fieldMap as $type => $file) {
                if ($file instanceof UploadedFile) {
                    $extension = strtolower($file->guessExtension() ?: 'pdf');
                    $customFilename = sprintf('vendor_%d_%s.%s', $timestamp, $type, $extension);
                    $path = $this->fileStorage->store(
                        $file,
                        self::DOCUMENT_DIRECTORY,
                        self::DOCUMENT_DISK,
                        customFilename: $customFilename
                    );

                    $docNumber = match ($type) {
                        'npwp' => ! empty($data['tax_number']) ? trim((string) $data['tax_number']) : (! empty($data['nomor_npwp']) ? trim((string) $data['nomor_npwp']) : null),
                        'nib' => ! empty($data['nomor_nib']) ? trim((string) $data['nomor_nib']) : null,
                        'ktp' => ! empty($data['nomor_ktp']) ? trim((string) $data['nomor_ktp']) : null,
                        'sppkp' => ! empty($data['nomor_sppkp']) ? trim((string) $data['nomor_sppkp']) : null,
                        'akta' => ! empty($data['nomor_akta']) ? trim((string) $data['nomor_akta']) : null,
                        default => null,
                    };

                    $storedDocuments[] = [
                        'document_type' => $type,
                        'document_number' => $docNumber,
                        'file_path' => $path,
                    ];
                }
            }

            if (count($storedDocuments) === 0) {
                foreach ($documents as $document) {
                    $file = $document['file'] ?? null;
                    if (! $file instanceof UploadedFile) {
                        continue;
                    }

                    $documentType = (string) ($document['document_type'] ?? 'npwp');
                    $rawNumber = isset($document['document_number']) ? trim((string) $document['document_number']) : '';
                    $extension = strtolower($file->guessExtension() ?: 'pdf');
                    $customFilename = sprintf('vendor_%d_%s.%s', $timestamp, $documentType, $extension);

                    $storedDocuments[] = [
                        'document_type' => $documentType,
                        'document_number' => $rawNumber !== '' ? $rawNumber : null,
                        'file_path' => $this->fileStorage->store(
                            $file,
                            self::DOCUMENT_DIRECTORY,
                            self::DOCUMENT_DISK,
                            customFilename: $customFilename
                        ),
                    ];
                }
            }
        } catch (Throwable $e) {
            foreach ($storedDocuments as $stored) {
                $this->fileStorage->delete(self::DOCUMENT_DISK, $stored['file_path']);
            }

            throw $e;
        }

        return $storedDocuments;
    }
}
