<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 7 (design-db.md §4.20): `penerimaan_barang_item` → `goods_receipt_items`
 * with English columns, `rejection_photos` JSON array, and `accepted`/`rejected` statuses.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('penerimaan_barang_item', 'goods_receipt_items');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER INDEX penerimaan_barang_item_pkey RENAME TO goods_receipt_items_pkey');
            DB::statement('ALTER SEQUENCE penerimaan_barang_item_id_seq RENAME TO goods_receipt_items_id_seq');
            DB::statement('ALTER TABLE goods_receipt_items RENAME CONSTRAINT penerimaan_barang_item_penerimaan_barang_id_foreign TO goods_receipt_items_goods_receipt_id_foreign');
            DB::statement('ALTER TABLE goods_receipt_items RENAME CONSTRAINT penerimaan_barang_item_po_item_id_foreign TO goods_receipt_items_purchase_order_item_id_foreign');
        }

        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->renameIndex('penerimaan_barang_item_uuid_unique', 'goods_receipt_items_uuid_unique');
            $table->renameColumn('penerimaan_barang_id', 'goods_receipt_id');
            $table->renameColumn('po_item_id', 'purchase_order_item_id');
            $table->renameColumn('jumlah_diterima', 'received_quantity');
            $table->renameColumn('catatan', 'notes');
        });

        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->json('rejection_photos')->nullable()->after('notes');
            $table->string('status', 30)->nullable(false)->change();
        });

        // Migrate legacy single photo path & Indonesian status strings.
        $items = DB::table('goods_receipt_items')->get(['id', 'status', 'foto_bukti_penolakan']);
        foreach ($items as $item) {
            $mappedStatus = match ($item->status) {
                'diterima', 'accepted' => 'accepted',
                default => 'rejected',
            };

            $photos = null;
            if (is_string($item->foto_bukti_penolakan) && trim($item->foto_bukti_penolakan) !== '') {
                $photos = json_encode([trim($item->foto_bukti_penolakan)]);
            }

            DB::table('goods_receipt_items')
                ->where('id', $item->id)
                ->update([
                    'status' => $mappedStatus,
                    'rejection_photos' => $photos,
                ]);
        }

        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->dropColumn('foto_bukti_penolakan');
            $table->index('goods_receipt_id');
            $table->index('purchase_order_item_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->dropIndex(['goods_receipt_id']);
            $table->dropIndex(['purchase_order_item_id']);
            $table->dropIndex(['status']);
            $table->string('foto_bukti_penolakan', 500)->nullable()->after('notes');
        });

        $items = DB::table('goods_receipt_items')->get(['id', 'status', 'rejection_photos']);
        foreach ($items as $item) {
            $decoded = is_string($item->rejection_photos) ? json_decode($item->rejection_photos, true) : null;
            $firstPhoto = is_array($decoded) && isset($decoded[0]) && is_string($decoded[0]) ? $decoded[0] : null;
            $legacyStatus = $item->status === 'accepted' ? 'diterima' : 'ditolak';

            DB::table('goods_receipt_items')
                ->where('id', $item->id)
                ->update([
                    'status' => $legacyStatus,
                    'foto_bukti_penolakan' => $firstPhoto,
                ]);
        }

        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->dropColumn('rejection_photos');
            $table->string('status', 20)->change();
        });

        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->renameColumn('goods_receipt_id', 'penerimaan_barang_id');
            $table->renameColumn('purchase_order_item_id', 'po_item_id');
            $table->renameColumn('received_quantity', 'jumlah_diterima');
            $table->renameColumn('notes', 'catatan');
            $table->renameIndex('goods_receipt_items_uuid_unique', 'penerimaan_barang_item_uuid_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE goods_receipt_items RENAME CONSTRAINT goods_receipt_items_purchase_order_item_id_foreign TO penerimaan_barang_item_po_item_id_foreign');
            DB::statement('ALTER TABLE goods_receipt_items RENAME CONSTRAINT goods_receipt_items_goods_receipt_id_foreign TO penerimaan_barang_item_penerimaan_barang_id_foreign');
            DB::statement('ALTER SEQUENCE goods_receipt_items_id_seq RENAME TO penerimaan_barang_item_id_seq');
            DB::statement('ALTER INDEX goods_receipt_items_pkey RENAME TO penerimaan_barang_item_pkey');
        }

        Schema::rename('goods_receipt_items', 'penerimaan_barang_item');
    }
};

