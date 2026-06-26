<?php

use App\Enums\OpportunityItemType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cutover to the unified, Current-RMS-style opportunity line-item model.
 *
 * Renames the polymorphic catalogue reference (`item_type`/`item_id` →
 * `itemable_type`/`itemable_id`), introduces the structural-role `item_type`
 * column ({@see OpportunityItemType}), the materialised-path `path`
 * column (4 chars per tree level), and an optional `revenue_group_id`. The old
 * `section_id` linkage and `sort_order` are subsumed by `path`, so they (and the
 * `opportunity_sections` table) are dropped.
 *
 * Schema-only — any data truncation/backfill lives in a later phase so this can
 * run on a fresh database under RefreshDatabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->renameColumn('item_type', 'itemable_type');
            $table->renameColumn('item_id', 'itemable_id');
        });

        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->string('item_type')->default('product')->after('itemable_id');
            $table->string('path')->default('')->after('item_type');
            $table->unsignedBigInteger('revenue_group_id')->nullable()->after('path');
            $table->index(['opportunity_id', 'path']);
            $table->index('revenue_group_id');
        });

        // Drop the section linkage + sort_order (subsumed by path). The
        // section_id migration added an explicit secondary index on top of the
        // foreign key, and the original create migration indexed
        // (opportunity_id, sort_order) — both must be dropped before their
        // columns, otherwise SQLite rejects the column drop.
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->dropIndex(['section_id']);
            $table->dropConstrainedForeignId('section_id');
            $table->dropIndex(['opportunity_id', 'sort_order']);
            $table->dropColumn('sort_order');
        });

        Schema::dropIfExists('opportunity_sections');
    }

    public function down(): void
    {
        Schema::create('opportunity_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('opportunity_sections')->nullOnDelete();
            $table->string('auto_group_key')->nullable();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
            $table->index(['opportunity_id', 'auto_group_key']);
        });

        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->dropIndex(['opportunity_id', 'path']);
            $table->dropIndex(['revenue_group_id']);
            $table->dropColumn(['item_type', 'path', 'revenue_group_id']);
            $table->foreignId('section_id')->nullable()->after('opportunity_id')->constrained('opportunity_sections')->nullOnDelete();
            $table->index('section_id');
            $table->integer('sort_order')->default(0);
            $table->index(['opportunity_id', 'sort_order']);
        });

        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->renameColumn('itemable_type', 'item_type');
            $table->renameColumn('itemable_id', 'item_id');
        });
    }
};
