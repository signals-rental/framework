<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the nullable `section_id` link from a line item to a custom
 * `opportunity_sections` group (M8-3 grouping decision).
 *
 * REPLAY-SAFETY INVARIANT: `section_id` is managed ONLY by plain invocable
 * actions (AssignItemToSection / DeleteOpportunitySection's nullOnDelete) via a
 * plain `update()` / `saveQuietly()`. NO Verbs event, state, apply(), or handle()
 * may ever read or write it — the `opportunity_items` projection is rebuilt from
 * the event stream on replay, and the only column the stream does not own is what
 * lets a replay preserve section assignments. The constraint uses nullOnDelete so
 * deleting a section drops its lines back to auto-grouping rather than removing
 * them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->foreignId('section_id')
                ->nullable()
                ->after('opportunity_id')
                ->constrained('opportunity_sections')
                ->nullOnDelete();

            // Explicit index for section grouping reads (the foreign key
            // constraint alone does not create a queryable index on SQLite).
            $table->index('section_id');
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            // Drop the FK + its auto-created index BEFORE the column so the
            // rollback reverses cleanly on SQLite (which rebuilds the table and
            // cannot drop a column still referenced by a constraint/index).
            $table->dropForeign(['section_id']);
            $table->dropIndex(['section_id']);
            $table->dropColumn('section_id');
        });
    }
};
