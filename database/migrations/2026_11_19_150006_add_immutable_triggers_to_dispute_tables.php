<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BR-17 / NFR-07 (design-db.md §7): `dispute_logs` rejects every UPDATE/DELETE, and a `resolved`
 * dispute can no longer be changed or deleted, even from a superuser session.
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

            CREATE TRIGGER dispute_logs_immutable
            BEFORE UPDATE OR DELETE ON dispute_logs
            FOR EACH ROW EXECUTE FUNCTION reject_update_delete();

            CREATE OR REPLACE FUNCTION reject_resolved_dispute_change() RETURNS trigger AS $$
            BEGIN
              IF OLD.status = 'resolved' THEN
                RAISE EXCEPTION 'Resolved dispute % is immutable', OLD.id;
              END IF;
              IF TG_OP = 'DELETE' THEN
                RETURN OLD;
              END IF;
              RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER disputes_resolved_immutable
            BEFORE UPDATE OR DELETE ON disputes
            FOR EACH ROW EXECUTE FUNCTION reject_resolved_dispute_change();
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

        DB::unprepared('DROP TRIGGER IF EXISTS disputes_resolved_immutable ON disputes');
        DB::unprepared('DROP FUNCTION IF EXISTS reject_resolved_dispute_change()');
        DB::unprepared('DROP TRIGGER IF EXISTS dispute_logs_immutable ON dispute_logs');

        // Shared with negotiation_messages; drop it only when no trigger uses it any more.
        $stillUsed = DB::selectOne("SELECT COUNT(*) AS total FROM pg_trigger WHERE tgfoid = 'reject_update_delete'::regproc")->total > 0;
        if (! $stillUsed) {
            DB::unprepared('DROP FUNCTION IF EXISTS reject_update_delete()');
        }
    }
};
