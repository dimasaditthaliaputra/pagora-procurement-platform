<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 3 (design-db.md §4.12): per-Tenant commodity activation and shrinkage tolerance override.
 * Until now every commodity was usable by every Tenant, so existing Tenants start with all
 * active commodities activated to keep current behavior.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenant_commodities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('commodity_id')->constrained('commodities')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->decimal('shrinkage_tolerance', 5, 2)->nullable()->comment('Null = pakai commodities.default_shrinkage_tolerance');
            $table->timestamps();

            $table->unique(['tenant_id', 'commodity_id']);
        });

        $commodityIds = DB::table('commodities')->whereNull('deleted_at')->where('is_active', true)->pluck('id');

        DB::table('tenants')->orderBy('id')->pluck('id')->each(function (int $tenantId) use ($commodityIds): void {
            $commodityIds->chunk(500)->each(function ($chunk) use ($tenantId): void {
                DB::table('tenant_commodities')->insert(
                    $chunk->map(fn (int $commodityId): array => [
                        'tenant_id' => $tenantId,
                        'commodity_id' => $commodityId,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->all()
                );
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_commodities');
    }
};
