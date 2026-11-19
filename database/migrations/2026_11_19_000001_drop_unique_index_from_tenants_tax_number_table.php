<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop unique constraint from `tenants.tax_number` (NPWP).
 * Allows multi-branch companies under the same headquarters (same NPWP)
 * while business logic and verification guards mitigate spam and impersonation.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'])) {
            DB::statement('DROP INDEX IF EXISTS ux_tenants_tax_number');
        } else {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropUnique('ux_tenants_tax_number');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'])) {
            DB::statement('CREATE UNIQUE INDEX ux_tenants_tax_number ON tenants (tax_number) WHERE tax_number IS NOT NULL AND deleted_at IS NULL');
        } else {
            Schema::table('tenants', function (Blueprint $table) {
                $table->unique('tax_number', 'ux_tenants_tax_number');
            });
        }
    }
};
