<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `opportunities` table is the read-optimised projection of the
 * event-sourced opportunity lifecycle. Columns are RMS-aligned (matching the
 * Current RMS schema) so list views, the API, and the availability engine query
 * the same shape they would in a non-event-sourced system. All mutations flow
 * through Verbs events whose handle() methods dual-write this table.
 *
 * Money columns are INTEGER minor units (pence/cents/fils). Dates are stored in
 * UTC. The two-axis state model lives in `state` (int) + `status` (int).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            // RMS-compatible small integer primary key. Unlike a DB
            // auto-increment column, this PK is application-assigned: the
            // CreateOpportunity action allocates it via SequenceAllocator and
            // bakes it into the OpportunityCreated event, so a truncate +
            // Verbs::replay() rebuild reproduces identical ids (replay-stable).
            $table->unsignedBigInteger('id')->primary();

            // Link to the Verbs event stream. The event-sourced state is keyed by
            // a snowflake; the projection keeps its own integer PK for RMS
            // compatibility, so this column bridges the two. Unique + indexed so
            // projections upsert by it and replay rebuilds deterministically.
            $table->unsignedBigInteger('state_id')->unique();

            // Identity / header
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('number')->nullable();
            $table->string('reference')->nullable();
            $table->text('external_description')->nullable();

            // Two-axis state model
            $table->smallInteger('state')->default(0);
            $table->smallInteger('status')->default(0);

            // Relationships (RMS field names). Nullable to keep the projection
            // self-contained and to tolerate partial header data on drafts.
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('owned_by')->nullable()->constrained('members')->nullOnDelete();

            // Hire / charge period (UTC)
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('charge_starts_at')->nullable();
            $table->dateTime('charge_ends_at')->nullable();

            // Calculated money totals — INTEGER minor units.
            $table->integer('charge_total')->default(0);
            $table->integer('rental_charge_total')->default(0);
            $table->integer('sale_charge_total')->default(0);
            $table->integer('service_charge_total')->default(0);
            $table->integer('charge_excluding_tax_total')->default(0);
            $table->integer('charge_including_tax_total')->default(0);
            $table->integer('tax_total')->default(0);

            // Flags
            $table->boolean('prices_include_tax')->default(false);
            $table->boolean('invoiced')->default(false);

            // Flexible config
            $table->jsonb('tag_list')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('state');
            $table->index('status');
            $table->index('number');
            $table->index('reference');
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
