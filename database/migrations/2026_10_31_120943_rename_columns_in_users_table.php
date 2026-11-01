<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 1 (design-db.md §4.2, §5): English column names on `users`
 * plus the foreign-key indexes the target schema requires.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('nama_lengkap', 'name');
            $table->renameColumn('perusahaan_id', 'tenant_id');
            $table->renameColumn('status_aktif', 'is_active');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users RENAME CONSTRAINT users_perusahaan_id_foreign TO users_tenant_id_foreign');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->index('role_id');
            $table->index('tenant_id');
            $table->index('vendor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role_id']);
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['vendor_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users RENAME CONSTRAINT users_tenant_id_foreign TO users_perusahaan_id_foreign');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'nama_lengkap');
            $table->renameColumn('tenant_id', 'perusahaan_id');
            $table->renameColumn('is_active', 'status_aktif');
        });
    }
};
