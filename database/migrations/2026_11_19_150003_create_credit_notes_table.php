<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credit notes: bill reductions produced by a dispute (design-db.md §4.26, FR-41, BR-16).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('credit_note_number', 50)->unique();
            $table->foreignId('invoice_id')->constrained('invoice')->restrictOnDelete();
            $table->foreignId('dispute_id')->constrained('disputes')->restrictOnDelete();
            $table->date('credit_date');
            $table->decimal('amount', 17, 2);
            $table->text('reason');
            $table->string('status', 30)->default('draft');
            $table->foreignId('vendor_confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('vendor_confirmed_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('invoice_id');
            $table->index('dispute_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
