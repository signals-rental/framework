<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Version-payload enrichment (opportunity-lifecycle.md §8.6, M7-B).
 *
 * Captures WHO a quote version was sent to and HOW, and WHO accepted it — fields
 * carried on the VersionSent / VersionAccepted events so they are recorded on the
 * immutable event stream (and replayable) before the stream is finalised at order
 * conversion.
 *
 *  - `sent_to`  — the member the version was sent to (FK → members, nullable).
 *  - `sent_via` — the channel it was sent through (email/portal/manual; nullable).
 *  - `accepted_by` — the user/member who accepted it (FK → members, nullable).
 *
 * `sent_to` / `accepted_by` reference members (a version is sent to / accepted by
 * a customer-side member — contact or organisation), unlike `created_by` which is
 * an internal users.id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_versions', function (Blueprint $table): void {
            $table->foreignId('sent_to')->nullable()->after('sent_at')
                ->constrained('members')->nullOnDelete();
            $table->string('sent_via')->nullable()->after('sent_to');
            $table->foreignId('accepted_by')->nullable()->after('accepted_at')
                ->constrained('members')->nullOnDelete();

            // Postgres does not auto-index FK columns.
            $table->index('sent_to', 'idx_opportunity_versions_sent_to');
            $table->index('accepted_by', 'idx_opportunity_versions_accepted_by');
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_versions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sent_to');
            $table->dropConstrainedForeignId('accepted_by');
            $table->dropColumn('sent_via');
        });
    }
};
