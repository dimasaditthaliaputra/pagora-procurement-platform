<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase returns: rejected goods sent back to the Vendor (design-db.md §4.27, FR-42, BR-27).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_returns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('return_number', 50)->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_order')->restrictOnDelete();
            $table->foreignId('dispute_id')->constrained('disputes')->restrictOnDelete();
            $table->date('return_date');
            $table->text('reason');
            $table->string('status', 30)->default('draft');
            $table->foreignId('vendor_confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('vendor_confirmed_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('purchase_order_id');
            $table->index('dispute_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_returns');
    }
};
