<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable();
            $table->json('matching_result')->nullable();
        });

        // Backfill vendor_id from the related purchase order.
        $invoices = DB::table('invoice')
            ->orderBy('id')
            ->get(['id', 'purchase_order_id']);

        foreach ($invoices as $invoice) {
            $vendorId = DB::table('purchase_order')
                ->where('id', $invoice->purchase_order_id)
                ->value('vendor_id');

            if ($vendorId === null) {
                throw new RuntimeException(
                    "Vendor tidak ditemukan untuk invoice ID {$invoice->id}."
                );
            }

            DB::table('invoice')
                ->where('id', $invoice->id)
                ->update(['vendor_id' => $vendorId]);
        }

        Schema::table('invoice', function (Blueprint $table) {
            $table->foreign('vendor_id')
                ->references('id')
                ->on('vendors')
                ->restrictOnDelete();

            $table->unique(
                ['vendor_id', 'nomor_invoice_vendor'],
                'invoice_vendor_invoice_number_unique'
            );

            $table->index(
                'status_matching',
                'invoice_status_matching_index'
            );
        });

        Schema::table('invoice', function (Blueprint $table) {
            $table->unsignedBigInteger('vendor_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoice', function (Blueprint $table) {
            $table->dropUnique('invoice_vendor_invoice_number_unique');
            $table->dropIndex('invoice_status_matching_index');
            $table->dropForeign(['vendor_id']);
            $table->dropColumn(['vendor_id', 'matching_result']);
        });
    }
};
