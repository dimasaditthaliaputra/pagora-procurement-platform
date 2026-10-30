<?php

declare(strict_types=1);

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
        if (! Schema::hasTable('undangan_vendor')) {
            Schema::create('undangan_vendor', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('nama_perusahaan', 200);
                $table->string('email', 150)->index();
                $table->string('kategori_komoditas', 100)->nullable();
                $table->text('pesan')->nullable();
                $table->string('token', 64)->unique();
                $table->string('status', 20)->default('pending')->index();
                $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('registered_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            Schema::table('undangan_vendor', function (Blueprint $table) {
                if (! Schema::hasColumn('undangan_vendor', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('undangan_vendor');
    }
};
