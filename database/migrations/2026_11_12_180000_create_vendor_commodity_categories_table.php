<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 2 / 3 (design-db.md §4.6): M:N pivot between `vendors` and `commodity_categories`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vendor_commodity_categories', function (Blueprint $table) {
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('commodity_category_id')->constrained('commodity_categories')->restrictOnDelete();
            $table->primary(['vendor_id', 'commodity_category_id']);
        });

        // Backfill existing vendors whose legacy `kategori_komoditas` matches an existing category name.
        $categoriesByName = DB::table('commodity_categories')
            ->whereNull('deleted_at')
            ->pluck('id', 'name');

        if ($categoriesByName->isNotEmpty()) {
            DB::table('vendors')
                ->whereNotNull('kategori_komoditas')
                ->orderBy('id')
                ->each(function (object $vendor) use ($categoriesByName): void {
                    $names = array_filter(array_map('trim', explode(',', (string) $vendor->kategori_komoditas)));

                    foreach ($names as $name) {
                        $categoryId = $categoriesByName[$name] ?? null;

                        if ($categoryId !== null) {
                            DB::table('vendor_commodity_categories')->insertOrIgnore([
                                'vendor_id' => $vendor->id,
                                'commodity_category_id' => $categoryId,
                            ]);
                        }
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_commodity_categories');
    }
};
