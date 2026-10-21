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
        Schema::create('invoice', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_order')->restrictOnDelete();
            $table->string('nomor_invoice_vendor', 100);
            $table->decimal('jumlah_tagihan', 17, 2);
            $table->string('file_invoice', 500)->nullable();
            $table->date('tanggal_invoice');
            $table->string('status_matching', 20)->default('pending');
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice');
    }
};
