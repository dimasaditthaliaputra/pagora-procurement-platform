<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Fixed platform roles (PRD §3). Values mirror `roles.name`.
 */
enum RoleName: string
{
    case Superadmin = 'superadmin';
    case Owner = 'owner';
    case Manajemen = 'manajemen';
    case Purchasing = 'purchasing';
    case QcGudang = 'qc_gudang';
    case Vendor = 'vendor';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::Owner => 'Owner',
            self::Manajemen => 'Manajemen',
            self::Purchasing => 'Purchasing / Finance',
            self::QcGudang => 'QC Gudang',
            self::Vendor => 'Vendor',
        };
    }

    /**
     * Roles belonging to a buyer company (Tenant).
     *
     * @return list<self>
     */
    public static function tenantRoles(): array
    {
        return [self::Owner, self::Manajemen, self::Purchasing, self::QcGudang];
    }

    /**
     * Roles an actor with this role may assign to other accounts (PRD BR-22, UC-08).
     *
     * @return list<self>
     */
    public function assignableRoles(): array
    {
        return match ($this) {
            self::Superadmin => self::cases(),
            self::Owner => [self::Manajemen, self::Purchasing, self::QcGudang],
            self::Vendor => [self::Vendor],
            default => [],
        };
    }
}
