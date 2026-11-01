<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 2 (design-db.md §4.7): `undangan_vendor` → `vendor_invitations` with English columns,
 * `tenants` link and status value `terdaftar` → `accepted` (Decision §12 no. 4).
 * `kategori_komoditas` stays a string until module 3 introduces `commodity_categories`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('undangan_vendor', 'vendor_invitations');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER INDEX undangan_vendor_pkey RENAME TO vendor_invitations_pkey');
            DB::statement('ALTER SEQUENCE undangan_vendor_id_seq RENAME TO vendor_invitations_id_seq');
            DB::statement('ALTER TABLE vendor_invitations RENAME CONSTRAINT undangan_vendor_invited_by_foreign TO vendor_invitations_invited_by_foreign');
        }

        Schema::table('vendor_invitations', function (Blueprint $table) {
            $table->renameIndex('undangan_vendor_uuid_unique', 'vendor_invitations_uuid_unique');
            $table->renameIndex('undangan_vendor_email_index', 'vendor_invitations_email_index');
            $table->renameIndex('undangan_vendor_token_unique', 'vendor_invitations_token_unique');
            $table->renameIndex('undangan_vendor_status_index', 'vendor_invitations_status_index');
            $table->renameColumn('nama_perusahaan', 'company_name');
            $table->renameColumn('pesan', 'message');
        });

        Schema::table('vendor_invitations', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('invited_by')
                ->constrained('tenants')->restrictOnDelete();
        });

        DB::table('vendor_invitations')->where('status', 'terdaftar')->update(['status' => 'accepted']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('vendor_invitations')->where('status', 'accepted')->update(['status' => 'terdaftar']);
        DB::table('vendor_invitations')->whereIn('status', ['expired', 'cancelled'])->update(['status' => 'pending']);

        Schema::table('vendor_invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('vendor_invitations', function (Blueprint $table) {
            $table->renameColumn('company_name', 'nama_perusahaan');
            $table->renameColumn('message', 'pesan');
            $table->renameIndex('vendor_invitations_status_index', 'undangan_vendor_status_index');
            $table->renameIndex('vendor_invitations_token_unique', 'undangan_vendor_token_unique');
            $table->renameIndex('vendor_invitations_email_index', 'undangan_vendor_email_index');
            $table->renameIndex('vendor_invitations_uuid_unique', 'undangan_vendor_uuid_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE vendor_invitations RENAME CONSTRAINT vendor_invitations_invited_by_foreign TO undangan_vendor_invited_by_foreign');
            DB::statement('ALTER SEQUENCE vendor_invitations_id_seq RENAME TO undangan_vendor_id_seq');
            DB::statement('ALTER INDEX vendor_invitations_pkey RENAME TO undangan_vendor_pkey');
        }

        Schema::rename('vendor_invitations', 'undangan_vendor');
    }
};
