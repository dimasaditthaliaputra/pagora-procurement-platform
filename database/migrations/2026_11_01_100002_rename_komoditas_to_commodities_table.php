<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 3 (design-db.md §4.11): `komoditas` → `commodities` with English columns.
 * The `kategori` string is moved to a foreign key by the next migration.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('DROP INDEX ux_komoditas_slug');
        DB::statement('DROP INDEX ux_komoditas_nama');
        DB::statement('DROP INDEX ux_komoditas_sku');

        Schema::rename('komoditas', 'commodities');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER INDEX komoditas_pkey RENAME TO commodities_pkey');
            DB::statement('ALTER SEQUENCE komoditas_id_seq RENAME TO commodities_id_seq');
        }

        Schema::table('commodities', function (Blueprint $table) {
            $table->renameIndex('komoditas_uuid_unique', 'commodities_uuid_unique');
            $table->renameColumn('nama_komoditas', 'name');
            $table->renameColumn('satuan', 'unit');
            $table->renameColumn('batas_susut', 'default_shrinkage_tolerance');
            $table->renameColumn('kode_referensi_api', 'api_reference_code');
            $table->renameColumn('spesifikasi', 'specification');
            $table->renameColumn('status', 'is_active');
        });

        DB::statement('CREATE UNIQUE INDEX ux_commodities_slug ON commodities (slug) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX ux_commodities_name ON commodities (name) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX ux_commodities_sku ON commodities (sku) WHERE sku IS NOT NULL AND deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX ux_commodities_slug');
        DB::statement('DROP INDEX ux_commodities_name');
        DB::statement('DROP INDEX ux_commodities_sku');

        Schema::table('commodities', function (Blueprint $table) {
            $table->renameColumn('name', 'nama_komoditas');
            $table->renameColumn('unit', 'satuan');
            $table->renameColumn('default_shrinkage_tolerance', 'batas_susut');
            $table->renameColumn('api_reference_code', 'kode_referensi_api');
            $table->renameColumn('specification', 'spesifikasi');
            $table->renameColumn('is_active', 'status');
            $table->renameIndex('commodities_uuid_unique', 'komoditas_uuid_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER SEQUENCE commodities_id_seq RENAME TO komoditas_id_seq');
            DB::statement('ALTER INDEX commodities_pkey RENAME TO komoditas_pkey');
        }

        Schema::rename('commodities', 'komoditas');

        DB::statement('CREATE UNIQUE INDEX ux_komoditas_slug ON komoditas (slug) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX ux_komoditas_nama ON komoditas (nama_komoditas) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX ux_komoditas_sku ON komoditas (sku) WHERE sku IS NOT NULL AND deleted_at IS NULL');
    }
};
