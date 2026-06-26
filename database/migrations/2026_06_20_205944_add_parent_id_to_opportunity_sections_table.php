<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds self-referential nesting to custom line-item groupings (sections) so a
 * section can sit inside another section (sub-groups) in the line-item editor.
 *
 * Like the rest of the sections table this is a PLAIN, non-event-sourced column:
 * the line -> section link stays on `opportunity_items.section_id` and is never
 * part of the Verbs stream, so a replay never touches the section hierarchy. The
 * FK self-references `opportunity_sections` and nulls on delete, so removing a
 * parent section promotes its children to the top level rather than cascading
 * them away.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_sections', function (Blueprint $table): void {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('opportunity_id')
                ->constrained('opportunity_sections')
                ->nullOnDelete();

            $table->index(['parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('opportunity_sections') || ! Schema::hasColumn('opportunity_sections', 'parent_id')) {
            return;
        }

        Schema::table('opportunity_sections', function (Blueprint $table): void {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                DB::statement('DROP INDEX IF EXISTS opportunity_sections_parent_id_sort_order_index');
            } else {
                $table->dropIndex(['parent_id', 'sort_order']);
            }

            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
