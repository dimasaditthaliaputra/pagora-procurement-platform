<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vendor', function (Blueprint $table) {
            $table->string('kategori_komoditas', 100)->nullable()->after('alamat');
            $table->string('dokumen_legalitas')->nullable()->after('kategori_komoditas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor', function (Blueprint $table) {
            $table->dropColumn(['kategori_komoditas', 'dokumen_legalitas']);
        });
    }
};
