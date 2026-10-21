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
        Schema::create('harga_referensi_komoditas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('komoditas_id')->constrained('komoditas')->restrictOnDelete();
            $table->date('tanggal');
            $table->decimal('harga', 15, 2);
            $table->string('sumber_data', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['komoditas_id', 'tanggal'], 'ix_harga_ref');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('harga_referensi_komoditas');
    }
};
