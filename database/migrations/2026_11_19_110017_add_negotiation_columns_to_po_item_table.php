<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-item negotiation (design-db.md §4.16, FR-19..21):
 * - `estimated_unit_price`: Tenant budget estimate, never sent to the Vendor (BR-02).
 * - `harga_satuan_po` / `subtotal`: Tenant's current proposal while negotiating, final price once
 *   agreed; empty until Purchasing proposes a price.
 * - `status_item`: `menunggu` → `pending` (`pending`, `countered`, `agreed`, `removed`).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('po_item', function (Blueprint $table): void {
            $table->decimal('estimated_unit_price', 15, 2)->nullable()->after('jumlah_pesanan');
        });

        DB::table('po_item')->update(['estimated_unit_price' => DB::raw('harga_satuan_po')]);
        DB::table('po_item')->where('status_item', 'menunggu')->update(['status_item' => 'pending']);

        Schema::table('po_item', function (Blueprint $table): void {
            $table->decimal('estimated_unit_price', 15, 2)->nullable(false)->change();
            $table->decimal('harga_satuan_po', 15, 2)->nullable()->change();
            $table->decimal('subtotal', 17, 2)->nullable()->change();
            $table->string('status_item', 30)->default('pending')->change();
            $table->index('status_item');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('po_item')->whereNull('harga_satuan_po')->update([
            'harga_satuan_po' => DB::raw('estimated_unit_price'),
            'subtotal' => DB::raw('jumlah_pesanan * estimated_unit_price'),
        ]);
        DB::table('po_item')->where('status_item', 'pending')->update(['status_item' => 'menunggu']);

        Schema::table('po_item', function (Blueprint $table): void {
            $table->dropIndex(['status_item']);
            $table->decimal('harga_satuan_po', 15, 2)->nullable(false)->change();
            $table->decimal('subtotal', 17, 2)->nullable(false)->change();
            $table->string('status_item', 30)->default('menunggu')->change();
            $table->dropColumn('estimated_unit_price');
        });
    }
};
