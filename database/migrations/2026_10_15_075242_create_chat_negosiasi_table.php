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
        Schema::create('chat_negosiasi', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_order')->restrictOnDelete();
            $table->foreignId('pengirim_id')->constrained('users')->restrictOnDelete();
            $table->text('pesan_teks')->nullable();
            $table->decimal('nominal_tawar', 15, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['purchase_order_id', 'created_at'], 'ix_chat_po');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_negosiasi');
    }
};
