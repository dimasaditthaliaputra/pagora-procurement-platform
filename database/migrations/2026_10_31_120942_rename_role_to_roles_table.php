<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 1 (design-db.md §5): `role` → `roles` with English columns,
 * and the `buyer` role value → `purchasing` (PRD Q-27).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('role', 'roles');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER INDEX role_pkey RENAME TO roles_pkey');
            DB::statement('ALTER SEQUENCE role_id_seq RENAME TO roles_id_seq');
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->renameColumn('nama_role', 'name');
            $table->renameColumn('deskripsi', 'description');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->renameIndex('role_nama_role_unique', 'roles_name_unique');
        });

        DB::table('roles')->where('name', 'buyer')->update(['name' => 'purchasing']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->where('name', 'purchasing')->update(['name' => 'buyer']);

        Schema::table('roles', function (Blueprint $table) {
            $table->renameIndex('roles_name_unique', 'role_nama_role_unique');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->renameColumn('name', 'nama_role');
            $table->renameColumn('description', 'deskripsi');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER SEQUENCE roles_id_seq RENAME TO role_id_seq');
            DB::statement('ALTER INDEX roles_pkey RENAME TO role_pkey');
        }

        Schema::rename('roles', 'role');
    }
};
