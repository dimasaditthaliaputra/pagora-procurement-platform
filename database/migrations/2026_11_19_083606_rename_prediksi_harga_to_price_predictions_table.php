<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 5 (design-db.md §4.14): `prediksi_harga` → `price_predictions` with English columns.
 * Every forecast run inserts a new batch (same `calculated_at`), so the latest batch per
 * commodity is looked up by (`commodity_id`, `calculated_at`). `purchase_order.prediksi_harga_id`
 * keeps pointing here; PostgreSQL follows the rename.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('prediksi_harga', 'price_predictions');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER INDEX prediksi_harga_pkey RENAME TO price_predictions_pkey');
            DB::statement('ALTER SEQUENCE prediksi_harga_id_seq RENAME TO price_predictions_id_seq');
            DB::statement('ALTER TABLE price_predictions RENAME CONSTRAINT prediksi_harga_komoditas_id_foreign TO price_predictions_commodity_id_foreign');
        }

        Schema::table('price_predictions', function (Blueprint $table): void {
            $table->renameIndex('prediksi_harga_uuid_unique', 'price_predictions_uuid_unique');
            $table->renameColumn('komoditas_id', 'commodity_id');
            $table->renameColumn('tanggal_prediksi', 'prediction_date');
            $table->renameColumn('harga_prediksi', 'predicted_price');
            $table->renameColumn('harga_pagu_rekomendasi', 'recommended_ceiling_price');
            $table->renameColumn('dihitung_pada', 'calculated_at');
        });

        Schema::table('price_predictions', function (Blueprint $table): void {
            $table->index(['commodity_id', 'prediction_date']);
            $table->index(['commodity_id', 'calculated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_predictions', function (Blueprint $table): void {
            $table->dropIndex(['commodity_id', 'calculated_at']);
            $table->dropIndex(['commodity_id', 'prediction_date']);
        });

        Schema::table('price_predictions', function (Blueprint $table): void {
            $table->renameColumn('commodity_id', 'komoditas_id');
            $table->renameColumn('prediction_date', 'tanggal_prediksi');
            $table->renameColumn('predicted_price', 'harga_prediksi');
            $table->renameColumn('recommended_ceiling_price', 'harga_pagu_rekomendasi');
            $table->renameColumn('calculated_at', 'dihitung_pada');
            $table->renameIndex('price_predictions_uuid_unique', 'prediksi_harga_uuid_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE price_predictions RENAME CONSTRAINT price_predictions_commodity_id_foreign TO prediksi_harga_komoditas_id_foreign');
            DB::statement('ALTER SEQUENCE price_predictions_id_seq RENAME TO prediksi_harga_id_seq');
            DB::statement('ALTER INDEX price_predictions_pkey RENAME TO prediksi_harga_pkey');
        }

        Schema::rename('price_predictions', 'prediksi_harga');
    }
};
