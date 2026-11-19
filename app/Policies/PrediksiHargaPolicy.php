<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PrediksiHarga;
use App\Models\User;

/**
 * Dasbor Analitik: price trend & Harga Pagu (UC-11, FR-15, FR-16).
 * Ceiling prices are internal to the Tenant and never reach Vendors (BR-02).
 */
class PrediksiHargaPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->tenant_id === null || $user->isVendor()) {
            return false;
        }

        return $user->hasPermission('harga_pagu.view_any');
    }

    /**
     * Pull the latest BI prices and forecast for one commodity on demand (UC-11 step 2).
     */
    public function refresh(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, PrediksiHarga $prediksiHarga): bool
    {
        return $this->viewAny($user);
    }
}
