<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * design-db.md §4.17: `chat_negosiasi` → `negotiation_messages` with per-item offers
 * (`purchase_order_item_id`) and a message `type` (FR-19, UC-12/13).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('chat_negosiasi', 'negotiation_messages');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER INDEX chat_negosiasi_pkey RENAME TO negotiation_messages_pkey');
            DB::statement('ALTER SEQUENCE chat_negosiasi_id_seq RENAME TO negotiation_messages_id_seq');
            DB::statement('ALTER TABLE negotiation_messages RENAME CONSTRAINT chat_negosiasi_pengirim_id_foreign TO negotiation_messages_sender_id_foreign');
            DB::statement('ALTER TABLE negotiation_messages RENAME CONSTRAINT chat_negosiasi_purchase_order_id_foreign TO negotiation_messages_purchase_order_id_foreign');
        }

        Schema::table('negotiation_messages', function (Blueprint $table): void {
            $table->dropIndex('ix_chat_po');
            $table->renameIndex('chat_negosiasi_uuid_unique', 'negotiation_messages_uuid_unique');
        });

        Schema::table('negotiation_messages', function (Blueprint $table): void {
            $table->renameColumn('pengirim_id', 'sender_id');
            $table->renameColumn('pesan_teks', 'message');
            $table->renameColumn('nominal_tawar', 'offer_amount');
        });

        Schema::table('negotiation_messages', function (Blueprint $table): void {
            $table->foreignId('purchase_order_item_id')
                ->nullable()
                ->after('purchase_order_id')
                ->constrained('po_item')
                ->restrictOnDelete();
            $table->string('type', 30)->default('text')->after('sender_id');
            // Polling reads "messages of this PO after message X", ordered by id.
            $table->index(['purchase_order_id', 'id']);
        });

        DB::table('negotiation_messages')->whereNotNull('offer_amount')->update(['type' => 'offer']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('negotiation_messages', function (Blueprint $table): void {
            $table->dropIndex(['purchase_order_id', 'id']);
            $table->dropConstrainedForeignId('purchase_order_item_id');
            $table->dropColumn('type');
        });

        Schema::table('negotiation_messages', function (Blueprint $table): void {
            $table->renameColumn('sender_id', 'pengirim_id');
            $table->renameColumn('message', 'pesan_teks');
            $table->renameColumn('offer_amount', 'nominal_tawar');
        });

        Schema::table('negotiation_messages', function (Blueprint $table): void {
            $table->renameIndex('negotiation_messages_uuid_unique', 'chat_negosiasi_uuid_unique');
            $table->index(['purchase_order_id', 'created_at'], 'ix_chat_po');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE negotiation_messages RENAME CONSTRAINT negotiation_messages_purchase_order_id_foreign TO chat_negosiasi_purchase_order_id_foreign');
            DB::statement('ALTER TABLE negotiation_messages RENAME CONSTRAINT negotiation_messages_sender_id_foreign TO chat_negosiasi_pengirim_id_foreign');
            DB::statement('ALTER SEQUENCE negotiation_messages_id_seq RENAME TO chat_negosiasi_id_seq');
            DB::statement('ALTER INDEX negotiation_messages_pkey RENAME TO chat_negosiasi_pkey');
        }

        Schema::rename('negotiation_messages', 'chat_negosiasi');
    }
};
