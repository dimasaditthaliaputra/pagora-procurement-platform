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
        Schema::create('purchase_order', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nomor_po', 50)->unique();
            $table->foreignId('vendor_id')->constrained('vendor')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('prediksi_harga_id')->nullable()->constrained('prediksi_harga')->restrictOnDelete();
            $table->decimal('harga_pagu_acuan', 15, 2)->nullable();
            $table->string('status', 30)->default('draft');
            $table->date('tanggal_po');
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'])) {
            DB::statement('CREATE INDEX ix_po_vendor ON purchase_order (vendor_id) WHERE deleted_at IS NULL');
            DB::statement('CREATE INDEX ix_po_status ON purchase_order (status) WHERE deleted_at IS NULL');
        } else {
            Schema::table('purchase_order', function (Blueprint $table) {
                $table->index('vendor_id', 'ix_po_vendor');
                $table->index('status', 'ix_po_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order');
    }
};
