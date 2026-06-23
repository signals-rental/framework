<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `auto_group_key` marker to custom line-item groupings (sections) for the
 * eager group-unification (every group is now a real `opportunity_sections` row).
 *
 * Auto-created groups (product-category buckets that used to be materialised only
 * at render time) are now persisted as real sections carrying the auto-group key
 * they were derived from (e.g. "auto:42", "auto:ungrouped", "auto:other"). The key
 * doubles as the find-or-create key on the add path: a new line looks up its
 * opportunity's section by `(opportunity_id, auto_group_key)` and joins it.
 *
 * User-created sections leave this NULL — they are not auto groups. Indexed for the
 * find-or-create lookup. Still a plain (non-event-sourced) column, like the rest of
 * the sections table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_sections', function (Blueprint $table): void {
            $table->string('auto_group_key')->nullable()->after('parent_id');

            $table->index(['opportunity_id', 'auto_group_key']);
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_sections', function (Blueprint $table): void {
            $table->dropIndex(['opportunity_id', 'auto_group_key']);
            $table->dropColumn('auto_group_key');
        });
    }
};
