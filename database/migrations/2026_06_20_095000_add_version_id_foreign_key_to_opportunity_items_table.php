<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Constrains `opportunity_items.version_id` to `opportunity_versions.id`
 * (opportunity-lifecycle.md §8).
 *
 * The column was created unconstrained in M3 (the opportunity_versions table did
 * not yet exist). Now that M4 has landed versioning, the FK can be declared for
 * referential integrity. The column is nullable (NULL = order / legacy /
 * non-versioned line) and the constraint uses nullOnDelete so deleting a version
 * detaches its items rather than cascading their removal.
 *
 * Note: `opportunities.active_version_id` deliberately remains unconstrained (it
 * uses a `0` sentinel for "no versions" and is written ahead of the version row in
 * the event-sourced replay path). That reasoning does NOT apply here — item
 * `version_id` is genuinely nullable and only ever set to a committed version id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->foreign('version_id')
                ->references('id')
                ->on('opportunity_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->dropForeign(['version_id']);
        });
    }
};
