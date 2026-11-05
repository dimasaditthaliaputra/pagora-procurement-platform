<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 6 (design-db.md §4.18): `pengiriman` → `shipments` with English columns,
 * `dispute_id` FK for reshipments, NOT NULL operational fields, `updated_at`, and
 * status values `disiapkan`/`dalam_perjalanan` → `in_transit`, `tiba` → `arrived`.
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const STATUS_MAP = [
        'disiapkan' => 'in_transit',
        'dalam_perjalanan' => 'in_transit',
        'tiba' => 'arrived',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('pengiriman', 'shipments');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER INDEX pengiriman_pkey RENAME TO shipments_pkey');
            DB::statement('ALTER SEQUENCE pengiriman_id_seq RENAME TO shipments_id_seq');
            DB::statement('ALTER TABLE shipments RENAME CONSTRAINT pengiriman_purchase_order_id_foreign TO shipments_purchase_order_id_foreign');
            DB::statement('ALTER TABLE shipments RENAME CONSTRAINT pengiriman_dilaporkan_oleh_foreign TO shipments_reported_by_foreign');
        }

        Schema::table('shipments', function (Blueprint $table): void {
            $table->renameIndex('pengiriman_uuid_unique', 'shipments_uuid_unique');
            $table->renameColumn('jalur_pengiriman', 'route');
            $table->renameColumn('nama_armada_ekspedisi', 'carrier_name');
            $table->renameColumn('no_polisi_armada', 'vehicle_plate_number');
            $table->renameColumn('perkiraan_tanggal_tiba', 'estimated_arrival_date');
            $table->renameColumn('dilaporkan_oleh', 'reported_by');
        });

        // Backfill any legacy nullable values before enforcing NOT NULL constraints.
        DB::table('shipments')->whereNull('route')->update(['route' => 'Darat']);
        DB::table('shipments')->whereNull('carrier_name')->update(['carrier_name' => 'Armada Vendor']);
        DB::table('shipments')->whereNull('vehicle_plate_number')->update(['vehicle_plate_number' => 'N 0000 XX']);
        DB::table('shipments')->whereNull('estimated_arrival_date')->update(['estimated_arrival_date' => now()->toDateString()]);

        foreach (self::STATUS_MAP as $old => $new) {
            DB::table('shipments')->where('status', $old)->update(['status' => $new]);
        }

        Schema::table('shipments', function (Blueprint $table): void {
            $table->foreignId('dispute_id')
                ->nullable()
                ->after('purchase_order_id')
                ->constrained('sengketa')
                ->restrictOnDelete();
            $table->string('route', 50)->nullable(false)->change();
            $table->string('carrier_name', 150)->nullable(false)->change();
            $table->string('vehicle_plate_number', 20)->nullable(false)->change();
            $table->date('estimated_arrival_date')->nullable(false)->change();
            $table->string('status', 30)->default('in_transit')->nullable(false)->change();
            $table->timestamp('updated_at')->nullable();
            $table->index('purchase_order_id');
            $table->index('status');
        });

        DB::table('shipments')->whereNull('updated_at')->update([
            'updated_at' => DB::raw('created_at'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('shipments')->where('status', 'in_transit')->update(['status' => 'dalam_perjalanan']);
        DB::table('shipments')->where('status', 'arrived')->update(['status' => 'tiba']);

        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropIndex(['purchase_order_id']);
            $table->dropIndex(['status']);
            $table->dropConstrainedForeignId('dispute_id');
            $table->dropColumn('updated_at');
            $table->string('route', 50)->nullable()->change();
            $table->string('carrier_name', 150)->nullable()->change();
            $table->string('vehicle_plate_number', 20)->nullable()->change();
            $table->date('estimated_arrival_date')->nullable()->change();
            $table->string('status', 30)->default('disiapkan')->change();
        });

        Schema::table('shipments', function (Blueprint $table): void {
            $table->renameColumn('route', 'jalur_pengiriman');
            $table->renameColumn('carrier_name', 'nama_armada_ekspedisi');
            $table->renameColumn('vehicle_plate_number', 'no_polisi_armada');
            $table->renameColumn('estimated_arrival_date', 'perkiraan_tanggal_tiba');
            $table->renameColumn('reported_by', 'dilaporkan_oleh');
            $table->renameIndex('shipments_uuid_unique', 'pengiriman_uuid_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE shipments RENAME CONSTRAINT shipments_reported_by_foreign TO pengiriman_dilaporkan_oleh_foreign');
            DB::statement('ALTER TABLE shipments RENAME CONSTRAINT shipments_purchase_order_id_foreign TO pengiriman_purchase_order_id_foreign');
            DB::statement('ALTER SEQUENCE shipments_id_seq RENAME TO pengiriman_id_seq');
            DB::statement('ALTER INDEX shipments_pkey RENAME TO pengiriman_pkey');
        }

        Schema::rename('shipments', 'pengiriman');
    }
};
