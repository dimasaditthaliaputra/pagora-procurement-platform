<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 2 (design-db.md §4.5): `dokumen_legalitas_vendor` → `vendor_legal_documents`
 * with English columns, an `updated_at` column and an index on `vendor_id`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('dokumen_legalitas_vendor', 'vendor_legal_documents');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER INDEX dokumen_legalitas_vendor_pkey RENAME TO vendor_legal_documents_pkey');
            DB::statement('ALTER SEQUENCE dokumen_legalitas_vendor_id_seq RENAME TO vendor_legal_documents_id_seq');
            DB::statement('ALTER TABLE vendor_legal_documents RENAME CONSTRAINT dokumen_legalitas_vendor_vendor_id_foreign TO vendor_legal_documents_vendor_id_foreign');
            DB::statement('ALTER TABLE vendor_legal_documents RENAME CONSTRAINT dokumen_legalitas_vendor_verif_by_foreign TO vendor_legal_documents_verified_by_foreign');
        }

        Schema::table('vendor_legal_documents', function (Blueprint $table) {
            $table->renameIndex('dokumen_legalitas_vendor_uuid_unique', 'vendor_legal_documents_uuid_unique');
            $table->renameColumn('jenis_dokumen', 'document_type');
            $table->renameColumn('nomor_dokumen', 'document_number');
            $table->renameColumn('file_url', 'file_path');
            $table->renameColumn('status_verifikasi', 'verification_status');
            $table->renameColumn('verif_by', 'verified_by');
            $table->renameColumn('diverifikasi_pada', 'verified_at');
            $table->renameColumn('alasan_penolakan', 'rejection_reason');
        });

        Schema::table('vendor_legal_documents', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable()->after('created_at');
            $table->index('vendor_id');
            $table->index('verification_status');
        });

        // The registration form never asked for a document type; it stored this generic value.
        DB::table('vendor_legal_documents')
            ->where('document_type', 'dokumen_legalitas')
            ->update(['document_type' => 'legal_document']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('vendor_legal_documents')
            ->where('document_type', 'legal_document')
            ->update(['document_type' => 'dokumen_legalitas']);

        Schema::table('vendor_legal_documents', function (Blueprint $table) {
            $table->dropIndex(['verification_status']);
            $table->dropIndex(['vendor_id']);
            $table->dropColumn('updated_at');
        });

        Schema::table('vendor_legal_documents', function (Blueprint $table) {
            $table->renameColumn('document_type', 'jenis_dokumen');
            $table->renameColumn('document_number', 'nomor_dokumen');
            $table->renameColumn('file_path', 'file_url');
            $table->renameColumn('verification_status', 'status_verifikasi');
            $table->renameColumn('verified_by', 'verif_by');
            $table->renameColumn('verified_at', 'diverifikasi_pada');
            $table->renameColumn('rejection_reason', 'alasan_penolakan');
            $table->renameIndex('vendor_legal_documents_uuid_unique', 'dokumen_legalitas_vendor_uuid_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE vendor_legal_documents RENAME CONSTRAINT vendor_legal_documents_verified_by_foreign TO dokumen_legalitas_vendor_verif_by_foreign');
            DB::statement('ALTER TABLE vendor_legal_documents RENAME CONSTRAINT vendor_legal_documents_vendor_id_foreign TO dokumen_legalitas_vendor_vendor_id_foreign');
            DB::statement('ALTER SEQUENCE vendor_legal_documents_id_seq RENAME TO dokumen_legalitas_vendor_id_seq');
            DB::statement('ALTER INDEX vendor_legal_documents_pkey RENAME TO dokumen_legalitas_vendor_pkey');
        }

        Schema::rename('vendor_legal_documents', 'dokumen_legalitas_vendor');
    }
};
