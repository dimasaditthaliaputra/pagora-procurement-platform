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
            $table->softDeletes();
        });

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'])) {
            DB::statement('CREATE INDEX ix_poitem_po ON po_item (purchase_order_id) WHERE deleted_at IS NULL');
        } else {
            Schema::table('po_item', function (Blueprint $table) {
                $table->index('purchase_order_id', 'ix_poitem_po');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('po_item');
    }
};
