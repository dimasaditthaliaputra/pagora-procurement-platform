<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * CREATE INDEX CONCURRENTLY tidak boleh dijalankan
     * di dalam transaksi PostgreSQL.
     */
    public $withinTransaction = false;

    /**
     * Menambahkan index untuk optimasi query PostgreSQL.
     */
    public function up(): void
    {
        // Lewati migration index khusus PostgreSQL jika menggunakan SQLite.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Index Purchase Order berdasarkan buyer dan status.
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS ix_po_tenant_status
            ON purchase_order (perusahaan_buyer_id, status)
            WHERE deleted_at IS NULL
        ");

        // Index Purchase Order berdasarkan vendor dan status.
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS ix_po_vendor_status
            ON purchase_order (vendor_id, status)
            WHERE deleted_at IS NULL
        ");

        // Index relasi PO Item ke Purchase Order.
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS ix_po_item_po
            ON po_item (purchase_order_id)
        ");

        // Index relasi Invoice ke Purchase Order.
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS ix_invoice_po
            ON invoice (purchase_order_id)
        ");

        // Index relasi Invoice Items ke Invoice.
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS ix_invoice_items_invoice
            ON invoice_items (invoice_id)
        ");
    }

    /**
     * Menghapus index jika migration di-rollback.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ix_invoice_items_invoice');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ix_invoice_po');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ix_po_item_po');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ix_po_vendor_status');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ix_po_tenant_status');
    }
};
