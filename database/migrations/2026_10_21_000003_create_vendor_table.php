<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vendor', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug', 160);
            $table->string('nama_perusahaan', 200);
            $table->string('email_kontak', 150);
            $table->string('no_telepon', 30)->nullable();
            $table->text('alamat')->nullable();
            $table->string('kategori_komoditas', 100)->nullable();
            $table->string('dokumen_legalitas')->nullable();
            $table->string('status_verifikasi', 20)->default('pending');
            $table->boolean('status_aktif')->default(true);
            $table->text('alasan_pemblokiran')->nullable();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('diblokir_pada')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'])) {
            DB::statement('CREATE UNIQUE INDEX ux_vendor_slug ON vendor (slug) WHERE deleted_at IS NULL');
            DB::statement('CREATE UNIQUE INDEX ux_vendor_email ON vendor (email_kontak) WHERE deleted_at IS NULL');
        } else {
            Schema::table('vendor', function (Blueprint $table) {
                $table->unique('slug', 'ux_vendor_slug');
                $table->unique('email_kontak', 'ux_vendor_email');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor');
    }
};
