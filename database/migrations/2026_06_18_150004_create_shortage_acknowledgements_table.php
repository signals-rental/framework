<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Confirmation-gate acknowledgements (shortage-resolution-sub-hires.md §7.3).
 *
 * The audit trail for "who confirmed this order knowing it was short, and what
 * exactly was the shortage at that time." Written by the confirmation gate when
 * a user proceeds past a shortage warning (Warn policy) or uses the
 * `can_ignore_shortages` permission to override a Block.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shortage_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')
                ->constrained('opportunities')
                ->cascadeOnDelete();
            // The acknowledging user is an application USER (auth()->id() is a
            // users.id), not a member — constraining to `members` broke inserts on
            // Postgres (the FK target is wrong); SQLite silently accepted it.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('acknowledged_at');
            $table->string('policy_at_time', 16);
            $table->boolean('permission_used')->default(false);
            $table->jsonb('shortages_snapshot');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index('opportunity_id', 'idx_shortage_acknowledgements_opportunity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shortage_acknowledgements');
    }
};
