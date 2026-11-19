<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 9 (design-db.md §4.24): `sengketa` → `disputes` with English columns, English status values
 * and the resolution columns of UC-18 (FR-38, FR-40, BR-15).
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const STATUS_MAP = [
        'terbuka' => 'open',
        'dalam_mediasi' => 'in_mediation',
        'selesai' => 'resolved',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('sengketa', 'disputes');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER INDEX sengketa_pkey RENAME TO disputes_pkey');
            DB::statement('ALTER SEQUENCE sengketa_id_seq RENAME TO disputes_id_seq');
            DB::statement('ALTER TABLE disputes RENAME CONSTRAINT sengketa_invoice_id_foreign TO disputes_invoice_id_foreign');
            DB::statement('ALTER TABLE disputes RENAME CONSTRAINT sengketa_dibuka_oleh_foreign TO disputes_opened_by_foreign');
            DB::statement('ALTER TABLE disputes RENAME CONSTRAINT sengketa_purchase_order_id_foreign TO disputes_purchase_order_id_foreign');
            DB::statement('ALTER TABLE disputes RENAME CONSTRAINT sengketa_purchase_order_item_id_foreign TO disputes_purchase_order_item_id_foreign');
        }

        Schema::table('disputes', function (Blueprint $table): void {
            $table->renameIndex('sengketa_uuid_unique', 'disputes_uuid_unique');
            $table->renameIndex('sengketa_purchase_order_id_index', 'disputes_purchase_order_id_index');
            $table->renameIndex('sengketa_purchase_order_item_id_index', 'disputes_purchase_order_item_id_index');
            $table->renameIndex('sengketa_status_index', 'disputes_status_index');
            $table->renameColumn('dibuka_oleh', 'opened_by');
            $table->renameColumn('tanggal_dibuka', 'opened_at');
            $table->renameColumn('tanggal_selesai', 'resolved_at');
        });

        foreach (self::STATUS_MAP as $old => $new) {
            DB::table('disputes')->where('status', $old)->update(['status' => $new]);
        }
        DB::table('disputes')->whereNull('reason')->update(['reason' => 'Ketidaksesuaian transaksi pengadaan.']);

        Schema::table('disputes', function (Blueprint $table): void {
            // Null = opened automatically by the system (design-db §4.24).
            $table->unsignedBigInteger('opened_by')->nullable()->change();
            $table->text('reason')->nullable(false)->change();
            $table->string('status', 30)->default('open')->change();
            $table->string('resolution_type', 30)->nullable()->after('status');
            $table->text('resolution_notes')->nullable()->after('resolution_type');
            $table->foreignId('vendor_confirmed_by')->nullable()->after('resolution_notes')->constrained('users')->restrictOnDelete();
            $table->timestamp('vendor_confirmed_at')->nullable()->after('vendor_confirmed_by');
            $table->foreignId('resolved_by')->nullable()->after('vendor_confirmed_at')->constrained('users')->restrictOnDelete();
            $table->index('invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table): void {
            $table->dropIndex(['invoice_id']);
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropConstrainedForeignId('vendor_confirmed_by');
            $table->dropColumn(['resolution_type', 'resolution_notes', 'vendor_confirmed_at']);
            $table->string('status', 30)->default('terbuka')->change();
        });

        foreach (self::STATUS_MAP as $old => $new) {
            DB::table('disputes')->where('status', $new)->update(['status' => $old]);
        }
        DB::table('disputes')->where('status', 'awaiting_response')->update(['status' => 'dalam_mediasi']);

        Schema::table('disputes', function (Blueprint $table): void {
            $table->renameIndex('disputes_uuid_unique', 'sengketa_uuid_unique');
            $table->renameIndex('disputes_purchase_order_id_index', 'sengketa_purchase_order_id_index');
            $table->renameIndex('disputes_purchase_order_item_id_index', 'sengketa_purchase_order_item_id_index');
            $table->renameIndex('disputes_status_index', 'sengketa_status_index');
        });

        Schema::table('disputes', function (Blueprint $table): void {
            $table->renameColumn('opened_by', 'dibuka_oleh');
            $table->renameColumn('opened_at', 'tanggal_dibuka');
            $table->renameColumn('resolved_at', 'tanggal_selesai');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE disputes RENAME CONSTRAINT disputes_purchase_order_item_id_foreign TO sengketa_purchase_order_item_id_foreign');
            DB::statement('ALTER TABLE disputes RENAME CONSTRAINT disputes_purchase_order_id_foreign TO sengketa_purchase_order_id_foreign');
            DB::statement('ALTER TABLE disputes RENAME CONSTRAINT disputes_opened_by_foreign TO sengketa_dibuka_oleh_foreign');
            DB::statement('ALTER TABLE disputes RENAME CONSTRAINT disputes_invoice_id_foreign TO sengketa_invoice_id_foreign');
            DB::statement('ALTER SEQUENCE disputes_id_seq RENAME TO sengketa_id_seq');
            DB::statement('ALTER INDEX disputes_pkey RENAME TO sengketa_pkey');
        }

        Schema::rename('disputes', 'sengketa');
    }
};
