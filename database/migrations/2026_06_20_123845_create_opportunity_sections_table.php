<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom line-item grouping (sections) for the opportunity line-item editor
 * (M8-3 grouping decision).
 *
 * Sections are plain Eloquent rows — deliberately NOT event-sourced. The
 * `opportunity_items` projection is rebuilt from the Verbs event stream on
 * replay; keeping the line -> section link (`opportunity_items.section_id`)
 * decoupled from that stream means replay never touches section assignments.
 * This table and its sibling column are therefore managed ONLY by plain
 * invocable actions (Create/Rename/Delete/Reorder Section + AssignItemToSection),
 * never by a Verbs event, apply(), or handle().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_sections', function (Blueprint $table): void {
            $table->id();

            // Parent opportunity — cascade so deleting an opportunity removes its
            // sections. The FK nullOnDelete on opportunity_items.section_id then
            // drops the lines back to auto-grouping.
            $table->foreignId('opportunity_id')->constrained('opportunities')->cascadeOnDelete();

            $table->string('name');
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index(['opportunity_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_sections');
    }
};
