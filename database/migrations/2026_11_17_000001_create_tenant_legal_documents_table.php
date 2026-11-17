<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenant_legal_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('document_number', 100)->nullable();
            $table->string('file_path', 500);
            $table->string('verification_status', 30)->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('verification_status');
        });

        $tenants = DB::table('tenants')->whereNotNull('legal_document_path')->get();
        foreach ($tenants as $tenant) {
            DB::table('tenant_legal_documents')->insert([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'document_type' => 'npwp',
                'document_number' => $tenant->tax_number,
                'file_path' => $tenant->legal_document_path,
                'verification_status' => $tenant->verification_status ?? 'pending',
                'verified_by' => $tenant->verified_by ?? null,
                'verified_at' => $tenant->verified_at ?? null,
                'rejection_reason' => $tenant->verification_note ?? null,
                'created_at' => $tenant->created_at ?? now(),
                'updated_at' => $tenant->updated_at ?? now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_legal_documents');
    }
};
