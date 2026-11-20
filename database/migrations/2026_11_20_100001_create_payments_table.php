<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments of an invoice through the payment gateway (design-db.md §4.23, FR-39, BR-26).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('invoice_id')->constrained('invoice')->restrictOnDelete();
            $table->string('payment_number', 50)->unique();
            $table->string('gateway_order_id', 100)->unique();
            $table->timestamp('payment_date')->nullable();
            $table->decimal('amount', 17, 2);
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_status', 30)->default('pending');
            $table->string('reference_number', 100)->nullable();
            $table->string('snap_token', 100)->nullable();
            $table->string('proof_file', 500)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('invoice_id');
            $table->index('payment_status');
            $table->index('reference_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
