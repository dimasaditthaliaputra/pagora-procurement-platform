<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 2 (design-db.md §4.29): `pengaturan` → `settings` with English columns.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('pengaturan', 'settings');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER INDEX pengaturan_pkey RENAME TO settings_pkey');
            DB::statement('ALTER SEQUENCE pengaturan_id_seq RENAME TO settings_id_seq');
            DB::statement('ALTER TABLE settings RENAME CONSTRAINT pengaturan_diubah_oleh_foreign TO settings_updated_by_foreign');
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->renameIndex('pengaturan_grup_index', 'settings_group_index');
            $table->renameIndex('pengaturan_kunci_unique', 'settings_key_unique');
            $table->renameColumn('grup', 'group');
            $table->renameColumn('kunci', 'key');
            $table->renameColumn('nilai', 'value');
            $table->renameColumn('terenkripsi', 'is_encrypted');
            $table->renameColumn('diubah_oleh', 'updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->renameColumn('group', 'grup');
            $table->renameColumn('key', 'kunci');
            $table->renameColumn('value', 'nilai');
            $table->renameColumn('is_encrypted', 'terenkripsi');
            $table->renameColumn('updated_by', 'diubah_oleh');
            $table->renameIndex('settings_key_unique', 'pengaturan_kunci_unique');
            $table->renameIndex('settings_group_index', 'pengaturan_grup_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE settings RENAME CONSTRAINT settings_updated_by_foreign TO pengaturan_diubah_oleh_foreign');
            DB::statement('ALTER SEQUENCE settings_id_seq RENAME TO pengaturan_id_seq');
            DB::statement('ALTER INDEX settings_pkey RENAME TO pengaturan_pkey');
        }

        Schema::rename('settings', 'pengaturan');
    }
};
