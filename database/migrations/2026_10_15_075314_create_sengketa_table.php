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
        Schema::create('sengketa', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('invoice_id')->constrained('invoice')->restrictOnDelete();
            $table->foreignId('dibuka_oleh')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('terbuka');
            $table->timestamp('tanggal_dibuka')->useCurrent();
            $table->timestamp('tanggal_selesai')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sengketa');
    }
};
