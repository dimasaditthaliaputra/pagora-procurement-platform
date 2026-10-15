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
        Schema::create('prediksi_harga', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('komoditas_id')->constrained('komoditas')->restrictOnDelete();
            $table->date('tanggal_prediksi');
            $table->decimal('harga_prediksi', 15, 2);
            $table->decimal('harga_pagu_rekomendasi', 15, 2);
            $table->timestamp('dihitung_pada')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prediksi_harga');
    }
};
