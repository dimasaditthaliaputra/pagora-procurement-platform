<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DokumenLegalitasVendor;
use App\Models\User;

/**
 * Vendor legal document verification (platform task).
 */
class DokumenLegalitasVendorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('dokumen_legalitas.view_any');
    }

    public function view(User $user, DokumenLegalitasVendor $dokumen): bool
    {
        return $user->hasPermission('dokumen_legalitas.view_any');
    }

    public function verify(User $user, DokumenLegalitasVendor $dokumen): bool
    {
        return $user->hasPermission('dokumen_legalitas.verify');
    }
}
