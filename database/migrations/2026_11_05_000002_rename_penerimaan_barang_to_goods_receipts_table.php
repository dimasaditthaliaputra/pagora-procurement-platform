<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Module 7 (design-db.md §4.19): `penerimaan_barang` → `goods_receipts`
 * with English column names and `shipment_id` FK to `shipments`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('penerimaan_barang', 'goods_receipts');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER INDEX penerimaan_barang_pkey RENAME TO goods_receipts_pkey');
            DB::statement('ALTER SEQUENCE penerimaan_barang_id_seq RENAME TO goods_receipts_id_seq');
            DB::statement('ALTER TABLE goods_receipts RENAME CONSTRAINT penerimaan_barang_purchase_order_id_foreign TO goods_receipts_purchase_order_id_foreign');
            DB::statement('ALTER TABLE goods_receipts RENAME CONSTRAINT penerimaan_barang_check_by_foreign TO goods_receipts_inspected_by_foreign');
        }

        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->renameIndex('penerimaan_barang_uuid_unique', 'goods_receipts_uuid_unique');
            $table->renameColumn('check_by', 'inspected_by');
            $table->renameColumn('no_polisi_dicocokkan', 'matched_plate_number');
            $table->renameColumn('tanggal_pemeriksaan', 'inspected_at');
            $table->renameColumn('catatan', 'notes');
        });

        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->foreignId('shipment_id')
                ->nullable()
                ->after('purchase_order_id')
                ->constrained('shipments')
                ->restrictOnDelete();
        });

        // Backfill `shipment_id` for any existing demo/legacy rows from `shipments`.
        $existingReceipts = DB::table('goods_receipts')->whereNull('shipment_id')->get();
        foreach ($existingReceipts as $receipt) {
            $shipmentId = DB::table('shipments')
                ->where('purchase_order_id', $receipt->purchase_order_id)
                ->orderByDesc('id')
                ->value('id');

            if ($shipmentId === null) {
                $shipmentId = DB::table('shipments')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'purchase_order_id' => $receipt->purchase_order_id,
                    'route' => 'Darat',
                    'carrier_name' => 'Armada Vendor',
                    'vehicle_plate_number' => $receipt->matched_plate_number ?: 'N 0000 XX',
                    'estimated_arrival_date' => now()->toDateString(),
                    'status' => 'arrived',
                    'reported_by' => $receipt->inspected_by,
                    'created_at' => $receipt->created_at ?? now(),
                    'updated_at' => $receipt->inspected_at ?? now(),
                ]);
            }

            DB::table('goods_receipts')
                ->where('id', $receipt->id)
                ->update(['shipment_id' => $shipmentId]);
        }

        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->unsignedBigInteger('shipment_id')->nullable(false)->change();
            $table->index('purchase_order_id');
            $table->index('shipment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropIndex(['purchase_order_id']);
            $table->dropIndex(['shipment_id']);
            $table->dropConstrainedForeignId('shipment_id');
        });

        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->renameColumn('inspected_by', 'check_by');
            $table->renameColumn('matched_plate_number', 'no_polisi_dicocokkan');
            $table->renameColumn('inspected_at', 'tanggal_pemeriksaan');
            $table->renameColumn('notes', 'catatan');
            $table->renameIndex('goods_receipts_uuid_unique', 'penerimaan_barang_uuid_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE goods_receipts RENAME CONSTRAINT goods_receipts_inspected_by_foreign TO penerimaan_barang_check_by_foreign');
            DB::statement('ALTER TABLE goods_receipts RENAME CONSTRAINT goods_receipts_purchase_order_id_foreign TO penerimaan_barang_purchase_order_id_foreign');
            DB::statement('ALTER SEQUENCE goods_receipts_id_seq RENAME TO penerimaan_barang_id_seq');
            DB::statement('ALTER INDEX goods_receipts_pkey RENAME TO penerimaan_barang_pkey');
        }

        Schema::rename('goods_receipts', 'penerimaan_barang');
    }
};

