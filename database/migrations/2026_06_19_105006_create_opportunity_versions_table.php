<?php

use App\Enums\VersionStatus;
use App\Enums\VersionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quote version projection (opportunity-lifecycle.md §8 — quote versioning).
 *
 * An opportunity in the Quotation state can carry multiple versions: sequential
 * REVISIONS (a new revision supersedes its parent) and parallel ALTERNATIVES
 * (concurrent options the customer chooses between). One version is the ACTIVE
 * version at any time — the opportunity's projected totals always mirror it, and
 * line items are scoped to it via `opportunity_items.version_id`.
 *
 * This is an event-sourced projection: every mutation flows through a Verbs
 * version event whose handle() dual-writes this row. The PK is application-
 * allocated (baked into the event payload via SequenceAllocator) so a
 * truncate + Verbs::replay() reproduces identical ids; `state_id` bridges to the
 * Verbs snowflake StateId. Money columns are INTEGER minor units (NET, ex-tax),
 * mirroring the `opportunities` totals shape (M3 tax model — all totals net).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_versions', function (Blueprint $table): void {
            // RMS-compatible integer primary key, application-assigned at
            // event-fire time (replay-stable) — NOT a DB auto-increment.
            $table->unsignedBigInteger('id')->primary();

            // Verbs snowflake StateId bridging the projection to the event stream.
            $table->unsignedBigInteger('state_id')->unique();

            $table->foreignId('opportunity_id')->constrained('opportunities')->cascadeOnDelete();

            // Per-opportunity sequential version number (replay-stable, derived
            // from the firing event payload, never a MAX() at apply-time).
            $table->integer('version_number');

            // Self-referential lineage: a revision points back at the version it
            // superseded; an alternative or the first version has no parent. The FK
            // is added after table creation (below) — Postgres needs the PK
            // constraint to exist before a self-referencing FK can target it.
            $table->unsignedBigInteger('parent_version_id')->nullable();

            // Forward lineage: when this version is superseded (by a newer revision,
            // or by the confirmed version on order conversion) this records WHICH
            // version replaced it. Carried in the VersionSuperseded event payload so
            // it survives replay; nullable (a live version has no successor). The FK
            // is added after table creation alongside parent_version_id.
            $table->unsignedBigInteger('superseded_by_version_id')->nullable();

            // 0 = Revision (sequential, supersedes parent), 1 = Alternative (parallel).
            $table->integer('version_type')->default(VersionType::Revision->value);

            $table->string('label')->nullable();

            // Exactly one version per opportunity is active; the opportunity's
            // totals mirror the active version's totals.
            $table->boolean('is_active')->default(false);

            // VersionStatus: 0 Draft, 1 Sent, 2 Accepted, 3 Declined, 4 Superseded.
            $table->integer('status')->default(VersionStatus::Draft->value);

            // NET (tax-exclusive) integer minor-unit totals, mirroring the
            // `opportunities` projection shape (M3 ex-tax tax model).
            $table->integer('charge_excluding_tax_total')->default(0);
            $table->integer('tax_total')->default(0);
            $table->integer('charge_including_tax_total')->default(0);
            $table->integer('charge_total')->default(0);

            // Optional free-text reason captured when a version is declined by the
            // customer (carried in the VersionDeclined event payload). Delete reasons
            // are NOT persisted here (the row is hard-deleted) — they go to the audit
            // trail's new_values only.
            $table->string('decline_reason')->nullable();

            $table->text('notes')->nullable();

            // The author is an application USER (auth()->id() is a users.id), not a
            // member — matching the M3 shortage-actor FK precedent (members was
            // wrong: it broke inserts on Postgres, SQLite silently accepted it).
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('declined_at')->nullable();

            $table->timestampsTz();

            $table->index('opportunity_id', 'idx_opportunity_versions_opportunity_id');
            $table->index(['opportunity_id', 'version_number'], 'idx_opportunity_versions_number');

            // Postgres does not auto-index FK-referencing columns. Index the lineage
            // FKs and the author FK explicitly.
            $table->index('parent_version_id', 'idx_opportunity_versions_parent');
            $table->index('superseded_by_version_id', 'idx_opportunity_versions_superseded_by');
            $table->index('created_by', 'idx_opportunity_versions_created_by');

            // Serves the alternative-cap count in VersionCreated::validate and the
            // accepted-version lookup in ConvertToOrder::resolveConfirmedVersion.
            $table->index(['opportunity_id', 'status'], 'idx_opportunity_versions_status');
        });

        // The self-referential FKs are added after the table (and its PK) exist so
        // Postgres can match the unique key on the referenced column.
        Schema::table('opportunity_versions', function (Blueprint $table): void {
            $table->foreign('parent_version_id')->references('id')->on('opportunity_versions')->nullOnDelete();
            $table->foreign('superseded_by_version_id')->references('id')->on('opportunity_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_versions');
    }
};
