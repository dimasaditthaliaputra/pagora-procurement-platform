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
        Schema::create('purchase_order', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nomor_po', 50)->unique();
            $table->foreignId('vendor_id')->constrained('vendor')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('prediksi_harga_id')->nullable()->constrained('prediksi_harga')->restrictOnDelete();
            $table->foreignId('perusahaan_buyer_id')->constrained('perusahaan_buyer')->restrictOnDelete();
            $table->decimal('harga_pagu_acuan', 15, 2)->nullable();
            $table->string('status', 30)->default('draft');
            $table->date('tanggal_po');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index('status', 'ix_po_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order');
    }
};
