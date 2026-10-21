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
        Schema::create('po_item', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_order')->restrictOnDelete();
            $table->foreignId('komoditas_id')->constrained('komoditas')->restrictOnDelete();
            $table->decimal('jumlah_pesanan', 15, 3);
            $table->decimal('harga_satuan_po', 15, 2);
            $table->decimal('subtotal', 17, 2);
            $table->decimal('harga_tawar_vendor', 15, 2)->nullable();
            $table->string('status_item', 30)->default('menunggu');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('po_item');
    }
};
