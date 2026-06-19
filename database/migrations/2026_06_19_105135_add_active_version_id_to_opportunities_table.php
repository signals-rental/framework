<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects the active quote version onto the opportunity (opportunity-lifecycle.md
 * §8.7). Set by the version events; the opportunity's totals and item scope follow
 * this version.
 *
 * `0` is the sentinel for "no versions" (legacy / non-versioned opportunities),
 * for which line items carry a NULL `version_id` and behaviour is unchanged. No FK
 * constraint is declared: the 0 sentinel is not a valid `opportunity_versions.id`,
 * and a constraint would couple the two projection rows in a way the event-sourced
 * write path (which sets the column before the version row may be committed in
 * replay) cannot guarantee in ordering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->unsignedBigInteger('active_version_id')->default(0)->after('status');
            $table->integer('version_count')->default(0)->after('active_version_id');
            $table->boolean('has_alternatives')->default(false)->after('version_count');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dropColumn(['active_version_id', 'version_count', 'has_alternatives']);
        });
    }
};
