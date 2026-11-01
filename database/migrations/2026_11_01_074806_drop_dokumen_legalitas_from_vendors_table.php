<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 2 (design-db.md §4.4, Decision §12 no. 5), step 3 of 3: drop `vendors.dokumen_legalitas`
 * after the previous migration copied and verified its contents.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $unmoved = DB::table('vendors')
            ->whereNotNull('dokumen_legalitas')
            ->where('dokumen_legalitas', '!=', '')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('vendor_legal_documents')
                    ->whereColumn('vendor_legal_documents.vendor_id', 'vendors.id')
                    ->whereColumn('vendor_legal_documents.file_path', 'vendors.dokumen_legalitas');
            })
            ->count();

        if ($unmoved > 0) {
            throw new RuntimeException("Kolom vendors.dokumen_legalitas tidak dihapus: {$unmoved} vendor belum dipindahkan.");
        }

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('dokumen_legalitas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Restores the column and fills it with each vendor's oldest legal document.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('dokumen_legalitas')->nullable()->after('kategori_komoditas');
        });

        DB::table('vendors')->orderBy('id')->each(function (object $vendor): void {
            $path = DB::table('vendor_legal_documents')
                ->where('vendor_id', $vendor->id)
                ->orderBy('id')
                ->value('file_path');

            if ($path !== null) {
                DB::table('vendors')->where('id', $vendor->id)->update(['dokumen_legalitas' => $path]);
            }
        });
    }
};
