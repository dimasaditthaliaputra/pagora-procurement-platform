<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusUndanganVendor;
use App\Mail\UndanganVendorMail;
use App\Models\UndanganVendor;
use App\Models\User;
use App\Models\Vendor;
use App\Repositories\Contracts\UndanganVendorRepositoryInterface;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function blokir(string $uuid, string $alasan, User $actor): Vendor
    {
        $vendor = $this->findByUuid($uuid);
        Gate::forUser($actor)->authorize('block', $vendor);

        if (! $vendor->is_active) {
            throw ValidationException::withMessages([
                'block_reason' => 'Vendor ini sudah dalam status diblokir.',
            ]);
        }

        return DB::transaction(function () use ($vendor, $alasan, $actor): Vendor {
            $this->vendorRepository->update($vendor, [
                'is_active' => false,
                'block_reason' => trim($alasan),
                'blocked_by' => $actor->id,
                'blocked_at' => now(),
            ]);

            // Deactivate all user logins belonging to this vendor
            $vendor->users()->update([
                'is_active' => false,
            ]);

            return $vendor->fresh(['blockedByUser']);
        });
    }

    /**
     * Unblock a vendor and reactivate its associated user accounts.
     *
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function bukaBlokir(string $uuid, User $actor): Vendor
    {
        $vendor = $this->findByUuid($uuid);
        Gate::forUser($actor)->authorize('unblock', $vendor);

        if ($vendor->is_active) {
            throw ValidationException::withMessages([
                'is_active' => 'Vendor ini sudah dalam status aktif.',
            ]);
        }

        return DB::transaction(function () use ($vendor): Vendor {
            $this->vendorRepository->update($vendor, [
                'is_active' => true,
                'block_reason' => null,
                'blocked_by' => null,
                'blocked_at' => null,
            ]);

            // Reactivate user logins belonging to this vendor
            $vendor->users()->update([
                'is_active' => true,
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
        if (Vendor::where('contact_email', $email)->exists()) {
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

            if ($existing !== null && $existing->status === StatusUndanganVendor::Pending) {
                $this->undanganRepository->update($existing, [
                    'company_name' => trim((string) $data['company_name']),
                    'kategori_komoditas' => ! empty($data['kategori_komoditas']) ? (string) $data['kategori_komoditas'] : null,
                    'message' => ! empty($data['message']) ? trim((string) $data['message']) : null,
                    'token' => $token,
                    'invited_by' => $actor->id,
                    'expires_at' => $expiresAt,
                ]);

                return $existing->fresh();
            }

            return $this->undanganRepository->create([
                'company_name' => trim((string) $data['company_name']),
                'email' => $email,
                'kategori_komoditas' => ! empty($data['kategori_komoditas']) ? (string) $data['kategori_komoditas'] : null,
                'message' => ! empty($data['message']) ? trim((string) $data['message']) : null,
                'token' => $token,
                'status' => StatusUndanganVendor::Pending,
                'invited_by' => $actor->id,
                'expires_at' => $expiresAt,
            ]);
        });

        // Send email via Dimas' module
        $roleName = $actor->role?->name;
        $roleLabel = match ($roleName) {
            'superadmin' => 'Superadmin',
            'manajemen' => 'Manajemen Internal',
            'purchasing' => 'Purchasing / Finance',
            default => 'Manajemen PAGORA',
        };

        $registrationUrl = route('register.vendor', ['invitation' => $undangan->token]);

        Mail::to($undangan->email)->send(
            new UndanganVendorMail(
                invitation: $undangan,
                inviterName: $actor->name,
                inviterRole: $roleLabel,
                registrationUrl: $registrationUrl,
            )
        );

        return $undangan;
    }
}
