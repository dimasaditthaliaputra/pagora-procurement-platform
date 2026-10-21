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
        Schema::create('pengiriman', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_order')->restrictOnDelete();
            $table->string('jalur_pengiriman', 50)->nullable();
            $table->string('nama_armada_ekspedisi', 150)->nullable();
            $table->string('no_polisi_armada', 20)->nullable();
            $table->date('perkiraan_tanggal_tiba')->nullable();
            $table->string('status', 30)->default('disiapkan');
            $table->foreignId('dilaporkan_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengiriman');
    }
};
