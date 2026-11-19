<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 5 (design-db.md §4.13): `harga_referensi_komoditas` → `commodity_reference_prices`
 * with English columns and UNIQUE (`commodity_id`, `date`, `source`) so BI syncs can upsert.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('harga_referensi_komoditas', 'commodity_reference_prices');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER INDEX harga_referensi_komoditas_pkey RENAME TO commodity_reference_prices_pkey');
            DB::statement('ALTER SEQUENCE harga_referensi_komoditas_id_seq RENAME TO commodity_reference_prices_id_seq');
            DB::statement('ALTER TABLE commodity_reference_prices RENAME CONSTRAINT harga_referensi_komoditas_komoditas_id_foreign TO commodity_reference_prices_commodity_id_foreign');
        }

        Schema::table('commodity_reference_prices', function (Blueprint $table): void {
            $table->dropIndex('ix_harga_ref');
            $table->renameIndex('harga_referensi_komoditas_uuid_unique', 'commodity_reference_prices_uuid_unique');
        });

        Schema::table('commodity_reference_prices', function (Blueprint $table): void {
            $table->renameColumn('komoditas_id', 'commodity_id');
            $table->renameColumn('tanggal', 'date');
            $table->renameColumn('harga', 'price');
            $table->renameColumn('sumber_data', 'source');
        });

        Schema::table('commodity_reference_prices', function (Blueprint $table): void {
            $table->index(['commodity_id', 'date']);
            $table->unique(['commodity_id', 'date', 'source']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commodity_reference_prices', function (Blueprint $table): void {
            $table->dropUnique(['commodity_id', 'date', 'source']);
            $table->dropIndex(['commodity_id', 'date']);
        });

        Schema::table('commodity_reference_prices', function (Blueprint $table): void {
            $table->renameColumn('commodity_id', 'komoditas_id');
            $table->renameColumn('date', 'tanggal');
            $table->renameColumn('price', 'harga');
            $table->renameColumn('source', 'sumber_data');
        });

        Schema::table('commodity_reference_prices', function (Blueprint $table): void {
            $table->renameIndex('commodity_reference_prices_uuid_unique', 'harga_referensi_komoditas_uuid_unique');
            $table->index(['komoditas_id', 'tanggal'], 'ix_harga_ref');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE commodity_reference_prices RENAME CONSTRAINT commodity_reference_prices_commodity_id_foreign TO harga_referensi_komoditas_komoditas_id_foreign');
            DB::statement('ALTER SEQUENCE commodity_reference_prices_id_seq RENAME TO harga_referensi_komoditas_id_seq');
            DB::statement('ALTER INDEX commodity_reference_prices_pkey RENAME TO harga_referensi_komoditas_pkey');
        }

        Schema::rename('commodity_reference_prices', 'harga_referensi_komoditas');
    }
};
