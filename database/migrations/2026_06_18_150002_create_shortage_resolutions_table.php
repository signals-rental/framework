<?php

use App\Enums\ShortageResolutionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persisted shortage resolutions (shortage-resolution-sub-hires.md §8.1).
 *
 * Shortages themselves are computed, not stored; resolutions ARE stored — they
 * are the durable record of a decision taken against a (transient) shortage.
 * Integer PK per the RMS-compatibility convention (the spec's uuid is relaxed to
 * match every other Signals table). `cost` is INTEGER minor units; `metadata` is
 * JSONB for resolver-specific data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shortage_resolutions', function (Blueprint $table): void {
            $table->id();
            $table->string('resolver_key', 64);
            $table->string('resolution_type', 32);
            $table->string('status', 32)->default(ShortageResolutionStatus::Pending->value);
            $table->integer('quantity_resolved')->default(0);
            $table->integer('cost')->nullable();
            $table->jsonb('metadata')->nullable();
            // The resolver/confirmer are application USERS (auth()->id() is a
            // users.id), not members — constraining to `members` broke inserts on
            // Postgres (the FK target is wrong); SQLite silently accepted it.
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('fulfilled_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['resolver_key', 'status'], 'idx_shortage_resolutions_resolver_status');
            $table->index('status', 'idx_shortage_resolutions_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shortage_resolutions');
    }
};
