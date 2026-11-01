<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Module 3 (design-db.md §4.11, §5): moves `commodities.kategori` (string) to
 * `commodities.commodity_category_id`. Every distinct value becomes a category; the migration
 * aborts before dropping the column if any commodity could not be linked.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('commodities', function (Blueprint $table) {
            $table->foreignId('commodity_category_id')->nullable()->after('name')
                ->constrained('commodity_categories')->restrictOnDelete();
        });

        DB::table('commodities')
            ->whereNotNull('kategori')
            ->where('kategori', '!=', '')
            ->distinct()
            ->pluck('kategori')
            ->each(function (string $name): void {
                $categoryId = DB::table('commodity_categories')->where('name', $name)->value('id')
                    ?? DB::table('commodity_categories')->insertGetId([
                        'uuid' => (string) Str::uuid(),
                        'name' => $name,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                DB::table('commodities')->where('kategori', $name)->update(['commodity_category_id' => $categoryId]);
            });

        $unlinked = DB::table('commodities')
            ->whereNotNull('kategori')
            ->where('kategori', '!=', '')
            ->whereNull('commodity_category_id')
            ->count();

        if ($unlinked > 0) {
            throw new RuntimeException("Kolom commodities.kategori tidak dihapus: {$unlinked} komoditas belum terhubung ke kategori.");
        }

        Schema::table('commodities', function (Blueprint $table) {
            $table->dropColumn('kategori');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Restores the string column from the category name. Categories created by `up()` stay.
     */
    public function down(): void
    {
        Schema::table('commodities', function (Blueprint $table) {
            $table->string('kategori', 50)->nullable()->after('name');
        });

        DB::table('commodity_categories')->orderBy('id')->each(function (object $category): void {
            DB::table('commodities')
                ->where('commodity_category_id', $category->id)
                ->update(['kategori' => Str::limit($category->name, 50, '')]);
        });

        Schema::table('commodities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commodity_category_id');
        });
    }
};
