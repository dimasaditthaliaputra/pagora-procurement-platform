<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('komoditas', function (Blueprint $table) {
            $table->string('sku', 50)->nullable()->after('uuid');
            $table->string('kategori', 50)->nullable()->after('nama_komoditas');
            $table->text('spesifikasi')->nullable()->after('satuan');
            $table->boolean('status_aktif')->default(true)->after('kode_referensi_api');
        });

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'])) {
            DB::statement('CREATE UNIQUE INDEX ux_komoditas_sku ON komoditas (sku) WHERE sku IS NOT NULL AND deleted_at IS NULL');
        } else {
            Schema::table('komoditas', function (Blueprint $table) {
                $table->unique('sku', 'ux_komoditas_sku');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'])) {
            DB::statement('DROP INDEX IF EXISTS ux_komoditas_sku');
        } else {
            Schema::table('komoditas', function (Blueprint $table) {
                $table->dropUnique('ux_komoditas_sku');
            });
        }

        Schema::table('komoditas', function (Blueprint $table) {
            $table->dropColumn(['sku', 'kategori', 'spesifikasi', 'status_aktif']);
        });
    }
};
