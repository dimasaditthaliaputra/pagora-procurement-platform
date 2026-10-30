<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\UndanganVendorMail;
use App\Models\UndanganVendor;
use App\Models\User;
use App\Models\Vendor;
use App\Repositories\Contracts\UndanganVendorRepositoryInterface;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VendorService
{
    public function __construct(
        protected VendorRepositoryInterface $vendorRepository,
        protected UndanganVendorRepositoryInterface $undanganRepository
    ) {}

    /**
     * Get paginated vendors with filters and sorting.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->vendorRepository->paginate($filters, $perPage);
    }

    /**
     * Find a vendor by UUID or fail.
     *
     * @throws ModelNotFoundException
     */
    public function findByUuid(string $uuid): Vendor
    {
        $vendor = $this->vendorRepository->findByUuid($uuid);

        if ($vendor === null) {
            throw new ModelNotFoundException('Data vendor tidak ditemukan.');
        }

        return $vendor;
    }

    /**
     * Block a vendor and deactivate its associated user accounts.
     *
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function blokir(string $uuid, string $alasan, User $actor): Vendor
    {
        $vendor = $this->findByUuid($uuid);

        if (! $vendor->status_aktif) {
            throw ValidationException::withMessages([
                'alasan_pemblokiran' => 'Vendor ini sudah dalam status diblokir.',
            ]);
        }

        return DB::transaction(function () use ($vendor, $alasan, $actor): Vendor {
            $this->vendorRepository->update($vendor, [
                'status_aktif' => false,
                'alasan_pemblokiran' => trim($alasan),
                'blocked_by' => $actor->id,
                'diblokir_pada' => now(),
            ]);

            // Deactivate all user logins belonging to this vendor
            $vendor->users()->update([
                'status_aktif' => false,
            ]);

            return $vendor->fresh(['blockedByUser']);
        });
    }

    /**
     * Unblock a vendor and reactivate its associated user accounts.
     *
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function bukaBlokir(string $uuid, User $actor): Vendor
    {
        $vendor = $this->findByUuid($uuid);

        if ($vendor->status_aktif) {
            throw ValidationException::withMessages([
                'status_aktif' => 'Vendor ini sudah dalam status aktif.',
            ]);
        }

        return DB::transaction(function () use ($vendor): Vendor {
            $this->vendorRepository->update($vendor, [
                'status_aktif' => true,
                'alasan_pemblokiran' => null,
                'blocked_by' => null,
                'diblokir_pada' => null,
            ]);

            // Reactivate user logins belonging to this vendor
            $vendor->users()->update([
                'status_aktif' => true,
            ]);

            return $vendor->fresh(['blockedByUser']);
        });
    }

    /**
     * Invite a new vendor candidate via email.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function undang(array $data, User $actor): UndanganVendor
    {
        $email = strtolower(trim((string) $data['email']));

        // Check if vendor with this email is already registered
        if (Vendor::where('email_kontak', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Email ini sudah terdaftar sebagai rekanan vendor di PAGORA.',
            ]);
        }

        // Check if an account already exists with this email
        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Email ini sudah terdaftar sebagai akun pengguna sistem di PAGORA.',
            ]);
        }

        $token = Str::random(40);
        $expiresAt = now()->addDays(7);

        $undangan = DB::transaction(function () use ($data, $email, $actor, $token, $expiresAt): UndanganVendor {
            $existing = $this->undanganRepository->findByEmail($email);

            if ($existing !== null && $existing->status === 'pending') {
                $this->undanganRepository->update($existing, [
                    'nama_perusahaan' => trim((string) $data['nama_perusahaan']),
                    'kategori_komoditas' => ! empty($data['kategori_komoditas']) ? (string) $data['kategori_komoditas'] : null,
                    'pesan' => ! empty($data['pesan']) ? trim((string) $data['pesan']) : null,
                    'token' => $token,
                    'invited_by' => $actor->id,
                    'expires_at' => $expiresAt,
                ]);

                return $existing->fresh();
            }

            return $this->undanganRepository->create([
                'nama_perusahaan' => trim((string) $data['nama_perusahaan']),
                'email' => $email,
                'kategori_komoditas' => ! empty($data['kategori_komoditas']) ? (string) $data['kategori_komoditas'] : null,
                'pesan' => ! empty($data['pesan']) ? trim((string) $data['pesan']) : null,
                'token' => $token,
                'status' => 'pending',
                'invited_by' => $actor->id,
                'expires_at' => $expiresAt,
            ]);
        });

        // Send email via Dimas' module
        $roleName = $actor->role?->nama_role;
        $roleLabel = match ($roleName) {
            'superadmin' => 'Superadmin',
            'manajemen' => 'Manajemen Internal',
            'buyer' => 'Tim Purchasing / Buyer',
            default => 'Manajemen PAGORA',
        };

        $registrationUrl = route('register.vendor', ['invitation' => $undangan->token]);

        Mail::to($undangan->email)->send(
            new UndanganVendorMail(
                invitation: $undangan,
                inviterName: $actor->nama_lengkap,
                inviterRole: $roleLabel,
                registrationUrl: $registrationUrl,
            )
        );

        return $undangan;
    }
}
