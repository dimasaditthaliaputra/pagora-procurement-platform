<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\KerjaSama;
use App\Models\User;

class KerjaSamaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isVendor() && $user->vendor_id !== null;
    }

    public function view(User $user, KerjaSama $kerjaSama): bool
    {
        return $user->isVendor()
            && $user->vendor_id !== null
            && (int) $user->vendor_id === (int) $kerjaSama->vendor_id
            && $kerjaSama->requested_by === 'tenant';
    }

    public function respond(User $user, KerjaSama $kerjaSama): bool
    {
        return $this->view($user, $kerjaSama);
    }
}