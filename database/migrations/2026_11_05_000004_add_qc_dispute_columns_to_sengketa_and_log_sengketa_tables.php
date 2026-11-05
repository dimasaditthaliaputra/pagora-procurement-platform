<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add per-item QC dispute linkage columns to `sengketa` and `log_sengketa`
 * (UC-15, FR-26, BR-10, design-db.md §4.24 & §4.25).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sengketa', function (Blueprint $table): void {
            $table->foreignId('purchase_order_id')
                ->nullable()
                ->after('uuid')
                ->constrained('purchase_order')
                ->restrictOnDelete();
            $table->foreignId('purchase_order_item_id')
                ->nullable()
                ->after('purchase_order_id')
                ->constrained('po_item')
                ->restrictOnDelete();
            $table->string('trigger_type', 30)->default('qc_rejected')->after('invoice_id');
            $table->text('reason')->nullable()->after('trigger_type');
            $table->string('status', 30)->default('terbuka')->change();
        });

        // Backfill `purchase_order_id`, `trigger_type`, and `reason` for existing invoice-based disputes.
        $disputes = DB::table('sengketa')->get(['id', 'invoice_id', 'reason']);
        foreach ($disputes as $dispute) {
            $poId = null;
            if ($dispute->invoice_id !== null) {
                $poId = DB::table('invoice')->where('id', $dispute->invoice_id)->value('purchase_order_id');
            }

            $firstLogMessage = DB::table('log_sengketa')
                ->where('sengketa_id', $dispute->id)
                ->orderBy('id')
                ->value('isi_pesan');

            $updates = [
                'trigger_type' => $dispute->invoice_id !== null ? 'amount_mismatch' : 'qc_rejected',
                'reason' => $dispute->reason ?? ($firstLogMessage ?: 'Ketidaksesuaian transaksi pengadaan.'),
            ];

            if ($poId !== null) {
                $updates['purchase_order_id'] = $poId;
            }

            DB::table('sengketa')->where('id', $dispute->id)->update($updates);
        }

        Schema::table('sengketa', function (Blueprint $table): void {
            $table->index('purchase_order_id');
            $table->index('purchase_order_item_id');
            $table->index('status');
        });

        Schema::table('log_sengketa', function (Blueprint $table): void {
            $table->string('type', 30)->default('note')->after('user_id');
            $table->index('sengketa_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('log_sengketa', function (Blueprint $table): void {
            $table->dropIndex(['sengketa_id']);
            $table->dropColumn('type');
        });

        Schema::table('sengketa', function (Blueprint $table): void {
            $table->dropIndex(['purchase_order_id']);
            $table->dropIndex(['purchase_order_item_id']);
            $table->dropIndex(['status']);
            $table->dropConstrainedForeignId('purchase_order_item_id');
            $table->dropConstrainedForeignId('purchase_order_id');
            $table->dropColumn(['trigger_type', 'reason']);
            $table->string('status', 20)->default('terbuka')->change();
        });
    }
};

