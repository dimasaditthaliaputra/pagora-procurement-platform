<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 4 (design-db.md §4.9): `kerja_sama` → `partnerships` with English columns, new
 * requester/block columns and status values `diajukan/aktif/ditolak/berakhir` →
 * `pending/agreed/rejected/cancelled` (plus the new `blocked`).
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const STATUS_MAP = [
        'diajukan' => 'pending',
        'aktif' => 'agreed',
        'ditolak' => 'rejected',
        'berakhir' => 'cancelled',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('kerja_sama', 'partnerships');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER INDEX kerja_sama_pkey RENAME TO partnerships_pkey');
            DB::statement('ALTER SEQUENCE kerja_sama_id_seq RENAME TO partnerships_id_seq');
            DB::statement('ALTER TABLE partnerships RENAME CONSTRAINT kerja_sama_id_perusahaan_foreign TO partnerships_tenant_id_foreign');
            DB::statement('ALTER TABLE partnerships RENAME CONSTRAINT kerja_sama_id_vendor_foreign TO partnerships_vendor_id_foreign');
        }

        Schema::table('partnerships', function (Blueprint $table) {
            $table->renameIndex('kerja_sama_uuid_unique', 'partnerships_uuid_unique');
            $table->renameIndex('uk_kerja_sama', 'partnerships_tenant_id_vendor_id_unique');
            $table->renameColumn('id_perusahaan', 'tenant_id');
            $table->renameColumn('id_vendor', 'vendor_id');
            $table->renameColumn('status_kerjasama', 'status');
            $table->renameColumn('catatan_penolakan', 'rejection_reason');
        });

        Schema::table('partnerships', function (Blueprint $table) {
            $table->string('requested_by', 20)->default('tenant')->after('vendor_id');
            $table->text('block_reason')->nullable();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('blocked_at')->nullable();
            $table->index('status');
        });

        foreach (self::STATUS_MAP as $old => $new) {
            DB::table('partnerships')->where('status', $old)->update(['status' => $new]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * `blocked` has no old equivalent and maps to `berakhir`.
     */
    public function down(): void
    {
        DB::table('partnerships')->where('status', 'blocked')->update(['status' => 'cancelled']);

        foreach (self::STATUS_MAP as $old => $new) {
            DB::table('partnerships')->where('status', $new)->update(['status' => $old]);
        }

        Schema::table('partnerships', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropConstrainedForeignId('blocked_by');
            $table->dropColumn(['requested_by', 'block_reason', 'blocked_at']);
        });

        Schema::table('partnerships', function (Blueprint $table) {
            $table->renameColumn('tenant_id', 'id_perusahaan');
            $table->renameColumn('vendor_id', 'id_vendor');
            $table->renameColumn('status', 'status_kerjasama');
            $table->renameColumn('rejection_reason', 'catatan_penolakan');
            $table->renameIndex('partnerships_tenant_id_vendor_id_unique', 'uk_kerja_sama');
            $table->renameIndex('partnerships_uuid_unique', 'kerja_sama_uuid_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE partnerships RENAME CONSTRAINT partnerships_vendor_id_foreign TO kerja_sama_id_vendor_foreign');
            DB::statement('ALTER TABLE partnerships RENAME CONSTRAINT partnerships_tenant_id_foreign TO kerja_sama_id_perusahaan_foreign');
            DB::statement('ALTER SEQUENCE partnerships_id_seq RENAME TO kerja_sama_id_seq');
            DB::statement('ALTER INDEX partnerships_pkey RENAME TO kerja_sama_pkey');
        }

        Schema::rename('partnerships', 'kerja_sama');
    }
};
