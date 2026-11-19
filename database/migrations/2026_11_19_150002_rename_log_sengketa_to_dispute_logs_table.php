<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 9 (design-db.md §4.25): `log_sengketa` → `dispute_logs` with English column names.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('log_sengketa', 'dispute_logs');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER INDEX log_sengketa_pkey RENAME TO dispute_logs_pkey');
            DB::statement('ALTER SEQUENCE log_sengketa_id_seq RENAME TO dispute_logs_id_seq');
            DB::statement('ALTER TABLE dispute_logs RENAME CONSTRAINT log_sengketa_sengketa_id_foreign TO dispute_logs_dispute_id_foreign');
            DB::statement('ALTER TABLE dispute_logs RENAME CONSTRAINT log_sengketa_user_id_foreign TO dispute_logs_user_id_foreign');
        }

        Schema::table('dispute_logs', function (Blueprint $table): void {
            $table->renameIndex('log_sengketa_uuid_unique', 'dispute_logs_uuid_unique');
            $table->renameIndex('log_sengketa_sengketa_id_index', 'dispute_logs_dispute_id_index');
            $table->renameColumn('sengketa_id', 'dispute_id');
            $table->renameColumn('isi_pesan', 'message');
            $table->renameColumn('file_bukti', 'attachment_path');
        });

        Schema::table('dispute_logs', function (Blueprint $table): void {
            // Null = written by the system (design-db §4.25).
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Indexes first: SQLite rebuilds a renamed index from its current column names.
        Schema::table('dispute_logs', function (Blueprint $table): void {
            $table->renameIndex('dispute_logs_uuid_unique', 'log_sengketa_uuid_unique');
            $table->renameIndex('dispute_logs_dispute_id_index', 'log_sengketa_sengketa_id_index');
        });

        Schema::table('dispute_logs', function (Blueprint $table): void {
            $table->renameColumn('dispute_id', 'sengketa_id');
            $table->renameColumn('message', 'isi_pesan');
            $table->renameColumn('attachment_path', 'file_bukti');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE dispute_logs RENAME CONSTRAINT dispute_logs_user_id_foreign TO log_sengketa_user_id_foreign');
            DB::statement('ALTER TABLE dispute_logs RENAME CONSTRAINT dispute_logs_dispute_id_foreign TO log_sengketa_sengketa_id_foreign');
            DB::statement('ALTER SEQUENCE dispute_logs_id_seq RENAME TO log_sengketa_id_seq');
            DB::statement('ALTER INDEX dispute_logs_pkey RENAME TO log_sengketa_pkey');
        }

        Schema::rename('dispute_logs', 'log_sengketa');
    }
};
