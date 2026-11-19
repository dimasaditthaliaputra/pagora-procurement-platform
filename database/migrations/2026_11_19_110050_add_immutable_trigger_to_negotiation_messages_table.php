<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * NFR-07 / BR-17 (design-db.md §7): negotiation messages are a permanent audit trail;
 * the database itself rejects UPDATE and DELETE, even from a superuser session.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_update_delete() RETURNS trigger AS $$
            BEGIN
              RAISE EXCEPTION 'Table % is immutable', TG_TABLE_NAME;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER negotiation_messages_immutable
            BEFORE UPDATE OR DELETE ON negotiation_messages
            FOR EACH ROW EXECUTE FUNCTION reject_update_delete();
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS negotiation_messages_immutable ON negotiation_messages');

        // The function is shared with other immutable tables (e.g. dispute_logs); drop it only when unused.
        $stillUsed = DB::selectOne("SELECT COUNT(*) AS total FROM pg_trigger WHERE tgfoid = 'reject_update_delete'::regproc")->total > 0;
        if (! $stillUsed) {
            DB::unprepared('DROP FUNCTION IF EXISTS reject_update_delete()');
        }
    }
};
