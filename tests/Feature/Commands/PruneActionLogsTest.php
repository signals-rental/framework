<?php

use App\Models\ActionLog;

it('prunes old action log entries', function () {
    ActionLog::factory()->create(['created_at' => now()->subMonths(13)]);
    ActionLog::factory()->create(['created_at' => now()->subMonths(6)]);
    ActionLog::factory()->create(['created_at' => now()]);

    $this->artisan('action-log:prune')
        ->assertSuccessful()
        ->expectsOutputToContain('Pruned 1 action log entries');

    expect(ActionLog::count())->toBe(2);
});

it('respects custom months option', function () {
    ActionLog::factory()->create(['created_at' => now()->subMonths(4)]);
    ActionLog::factory()->create(['created_at' => now()->subMonths(2)]);
    ActionLog::factory()->create(['created_at' => now()]);

    $this->artisan('action-log:prune --months=3')
        ->assertSuccessful()
        ->expectsOutputToContain('Pruned 1 action log entries');

    expect(ActionLog::count())->toBe(2);
});

it('reports zero when nothing to prune', function () {
    ActionLog::factory()->create(['created_at' => now()]);

    $this->artisan('action-log:prune')
        ->assertSuccessful()
        ->expectsOutputToContain('Pruned 0 action log entries');
});
