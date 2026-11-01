<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Module 2 (design-db.md §4.4, Decision §12 no. 5), step 1 and 2 of 3: copy `vendors.dokumen_legalitas`
 * into `vendor_legal_documents` and verify the result. The column itself is dropped by the next migration.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('vendors')
            ->whereNotNull('dokumen_legalitas')
            ->where('dokumen_legalitas', '!=', '')
            ->orderBy('id')
            ->each(function (object $vendor): void {
                $alreadyMoved = DB::table('vendor_legal_documents')
                    ->where('vendor_id', $vendor->id)
                    ->where('file_path', $vendor->dokumen_legalitas)
                    ->exists();

                if ($alreadyMoved) {
                    return;
                }

                DB::table('vendor_legal_documents')->insert([
                    'uuid' => (string) Str::uuid(),
                    'vendor_id' => $vendor->id,
                    'document_type' => 'legal_document',
                    'document_number' => null,
                    'file_path' => $vendor->dokumen_legalitas,
                    'verification_status' => $vendor->verification_status,
                    'created_at' => $vendor->created_at ?? now(),
                    'updated_at' => now(),
                ]);
            });

        $missing = DB::table('vendors')
            ->whereNotNull('dokumen_legalitas')
            ->where('dokumen_legalitas', '!=', '')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('vendor_legal_documents')
                    ->whereColumn('vendor_legal_documents.vendor_id', 'vendors.id')
                    ->whereColumn('vendor_legal_documents.file_path', 'vendors.dokumen_legalitas');
            })
            ->count();

        if ($missing > 0) {
            throw new RuntimeException("Pemindahan dokumen legalitas gagal diverifikasi: {$missing} vendor belum memiliki dokumennya di vendor_legal_documents.");
        }
    }

    /**
     * Reverse the migrations.
     *
     * Copied rows stay in `vendor_legal_documents`; they are valid documents and the old column
     * is still populated at this point, so there is nothing to undo.
     */
    public function down(): void
    {
        //
    }
};
