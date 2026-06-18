<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a nullable, unique idempotency key tying an audit row to the Verbs
     * event that produced it.
     *
     * Because the column is BOTH nullable and unique, multiple NULLs are
     * permitted on Postgres and SQLite alike (standard SQL treats NULLs as
     * distinct in unique indexes). All existing rows and every non
     * event-sourced audit row therefore stay untouched (verb_event_id NULL),
     * while each event-sourced audit row is unique per Verbs event id. That
     * uniqueness makes replay re-dispatch a no-op: the second insert collides on
     * the unique key and is swallowed by firstOrCreate().
     */
    public function up(): void
    {
        Schema::table('action_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('verb_event_id')->nullable()->after('metadata');
            $table->unique('verb_event_id', 'action_logs_verb_event_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('action_logs', function (Blueprint $table): void {
            $table->dropUnique('action_logs_verb_event_id_unique');
            $table->dropColumn('verb_event_id');
        });
    }
};
