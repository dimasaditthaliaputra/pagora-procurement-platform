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
        Schema::create('dokumen_legalitas_vendor', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained('vendor')->restrictOnDelete();
            $table->string('jenis_dokumen', 50);
            $table->string('nomor_dokumen', 100)->nullable();
            $table->string('file_url', 500);
            $table->string('status_verifikasi', 20)->default('pending');
            $table->foreignId('verif_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dokumen_legalitas_vendor');
    }
};
