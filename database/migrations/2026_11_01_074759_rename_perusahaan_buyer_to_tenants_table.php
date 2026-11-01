<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 2 (design-db.md §4.3): `perusahaan_buyer` → `tenants` with English columns,
 * verification columns and soft deletes. `tipe_paket` is dropped (PRD Q-18).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('perusahaan_buyer', 'tenants');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER INDEX perusahaan_buyer_pkey RENAME TO tenants_pkey');
            DB::statement('ALTER SEQUENCE perusahaan_buyer_id_seq RENAME TO tenants_id_seq');
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->renameIndex('perusahaan_buyer_uuid_unique', 'tenants_uuid_unique');
            $table->renameColumn('nama_perusahaan', 'company_name');
            $table->renameColumn('nomor_npwp', 'tax_number');
            $table->renameColumn('file_url', 'legal_document_path');
            $table->renameColumn('alamat', 'address');
            $table->renameColumn('status_aktif', 'is_active');
            $table->dropColumn('tipe_paket');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('verification_status', 30)->default('pending')->index()->after('address');
            $table->text('verification_note')->nullable()->after('verification_status');
            $table->foreignId('verified_by')->nullable()->after('verification_note')
                ->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->softDeletes();
        });

        // Tenants that already exist were operating before verification was tracked.
        DB::table('tenants')->update(['verification_status' => 'approved']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropIndex(['verification_status']);
            $table->dropColumn([
                'verification_status',
                'verification_note',
                'verified_by',
                'verified_at',
                'deleted_at',
            ]);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('tipe_paket', 30)->nullable();
            $table->renameColumn('company_name', 'nama_perusahaan');
            $table->renameColumn('tax_number', 'nomor_npwp');
            $table->renameColumn('legal_document_path', 'file_url');
            $table->renameColumn('address', 'alamat');
            $table->renameColumn('is_active', 'status_aktif');
            $table->renameIndex('tenants_uuid_unique', 'perusahaan_buyer_uuid_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER SEQUENCE tenants_id_seq RENAME TO perusahaan_buyer_id_seq');
            DB::statement('ALTER INDEX tenants_pkey RENAME TO perusahaan_buyer_pkey');
        }

        Schema::rename('tenants', 'perusahaan_buyer');
    }
};
