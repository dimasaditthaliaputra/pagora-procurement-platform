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
        Schema::create('kerja_sama', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('id_perusahaan')->constrained('perusahaan_buyer')->restrictOnDelete();
            $table->foreignId('id_vendor')->constrained('vendor')->restrictOnDelete();
            $table->string('status_kerjasama', 20)->default('diajukan');
            $table->text('catatan_penolakan')->nullable();
            $table->timestamps();

            $table->unique(['id_perusahaan', 'id_vendor'], 'uk_kerja_sama');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kerja_sama');
    }
};
