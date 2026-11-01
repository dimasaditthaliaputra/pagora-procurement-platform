<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 2 (design-db.md §4.4): `vendor` → `vendors` with English columns and `tax_number`.
 * `kategori_komoditas` stays until module 3 and `dokumen_legalitas` is moved and dropped
 * by the two migrations after the legal documents table is renamed.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('DROP INDEX ux_vendor_slug');
        DB::statement('DROP INDEX ux_vendor_email');

        Schema::rename('vendor', 'vendors');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER INDEX vendor_pkey RENAME TO vendors_pkey');
            DB::statement('ALTER SEQUENCE vendor_id_seq RENAME TO vendors_id_seq');
            DB::statement('ALTER TABLE vendors RENAME CONSTRAINT vendor_blocked_by_foreign TO vendors_blocked_by_foreign');
        }

        Schema::table('vendors', function (Blueprint $table) {
            $table->renameIndex('vendor_uuid_unique', 'vendors_uuid_unique');
            $table->renameColumn('nama_perusahaan', 'company_name');
            $table->renameColumn('email_kontak', 'contact_email');
            $table->renameColumn('no_telepon', 'phone');
            $table->renameColumn('alamat', 'address');
            $table->renameColumn('status_verifikasi', 'verification_status');
            $table->renameColumn('status_aktif', 'is_active');
            $table->renameColumn('alasan_pemblokiran', 'block_reason');
            $table->renameColumn('diblokir_pada', 'blocked_at');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->string('tax_number', 30)->nullable()->after('company_name');
            $table->index('verification_status');
        });

        // The old filter accepted these values although the app only ever stored approved/rejected.
        DB::table('vendors')->where('verification_status', 'terverifikasi')->update(['verification_status' => 'approved']);
        DB::table('vendors')->where('verification_status', 'ditolak')->update(['verification_status' => 'rejected']);

        DB::statement('CREATE UNIQUE INDEX ux_vendors_slug ON vendors (slug) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX ux_vendors_contact_email ON vendors (contact_email) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX ux_vendors_slug');
        DB::statement('DROP INDEX ux_vendors_contact_email');

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropIndex(['verification_status']);
            $table->dropColumn('tax_number');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->renameColumn('company_name', 'nama_perusahaan');
            $table->renameColumn('contact_email', 'email_kontak');
            $table->renameColumn('phone', 'no_telepon');
            $table->renameColumn('address', 'alamat');
            $table->renameColumn('verification_status', 'status_verifikasi');
            $table->renameColumn('is_active', 'status_aktif');
            $table->renameColumn('block_reason', 'alasan_pemblokiran');
            $table->renameColumn('blocked_at', 'diblokir_pada');
            $table->renameIndex('vendors_uuid_unique', 'vendor_uuid_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE vendors RENAME CONSTRAINT vendors_blocked_by_foreign TO vendor_blocked_by_foreign');
            DB::statement('ALTER SEQUENCE vendors_id_seq RENAME TO vendor_id_seq');
            DB::statement('ALTER INDEX vendors_pkey RENAME TO vendor_pkey');
        }

        Schema::rename('vendors', 'vendor');

        DB::statement('CREATE UNIQUE INDEX ux_vendor_slug ON vendor (slug) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX ux_vendor_email ON vendor (email_kontak) WHERE deleted_at IS NULL');
    }
};
