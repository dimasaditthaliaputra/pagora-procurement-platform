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
        Schema::create('komoditas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug', 160);
            $table->string('nama_komoditas', 150);
            $table->string('satuan', 20);
            $table->string('kode_referensi_api', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'])) {
            DB::statement('CREATE UNIQUE INDEX ux_komoditas_slug ON komoditas (slug) WHERE deleted_at IS NULL');
            DB::statement('CREATE UNIQUE INDEX ux_komoditas_nama ON komoditas (nama_komoditas) WHERE deleted_at IS NULL');
        } else {
            Schema::table('komoditas', function (Blueprint $table) {
                $table->unique('slug', 'ux_komoditas_slug');
                $table->unique('nama_komoditas', 'ux_komoditas_nama');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('komoditas');
    }
};
