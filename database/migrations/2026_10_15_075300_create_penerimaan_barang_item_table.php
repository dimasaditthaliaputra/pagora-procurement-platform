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
        Schema::create('penerimaan_barang_item', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('penerimaan_barang_id')->constrained('penerimaan_barang')->restrictOnDelete();
            $table->foreignId('po_item_id')->constrained('po_item')->restrictOnDelete();
            $table->decimal('jumlah_diterima', 15, 3);
            $table->string('status', 20);
            $table->text('catatan')->nullable();
            $table->string('foto_bukti_penolakan', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penerimaan_barang_item');
    }
};
