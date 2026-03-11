<?php

use App\Events\AuditableEvent;
use App\Models\ActionLog;
use App\Models\User;

it('creates an action log entry when an auditable event fires', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    event(new AuditableEvent(
        model: $user,
        action: 'updated',
        oldValues: ['name' => 'Old Name'],
        newValues: ['name' => 'New Name'],
    ));

    expect(ActionLog::count())->toBe(1);

    $log = ActionLog::first();
    expect($log->user_id)->toBe($user->id);
    expect($log->action)->toBe('updated');
    expect($log->auditable_type)->toBe(User::class);
    expect($log->auditable_id)->toBe($user->id);
    expect($log->old_values)->toBe(['name' => 'Old Name']);
    expect($log->new_values)->toBe(['name' => 'New Name']);
});

it('records null ip address and user agent in console context', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    event(new AuditableEvent($user, 'created'));

    $log = ActionLog::first();
    expect($log->ip_address)->toBeNull();
    expect($log->user_agent)->toBeNull();
});

it('handles events without authenticated user', function () {
    $user = User::factory()->create();

    event(new AuditableEvent($user, 'created'));

    $log = ActionLog::first();
    expect($log->user_id)->toBeNull();
    expect($log->action)->toBe('created');
});

it('silently handles exception when ActionLog creation fails', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $listener = new \App\Listeners\LogAction;

    // Create an event with a model that has no morph class mapping — this won't throw,
    // but we can verify the try/catch path by using a mock that forces an exception
    $event = new AuditableEvent($user, 'created');

    // Replace the model with one that throws on getMorphClass
    $mockModel = Mockery::mock(\App\Models\User::class)->makePartial();
    $mockModel->shouldReceive('getMorphClass')->andThrow(new \RuntimeException('Database error'));

    $event->model = $mockModel;

    // Should not throw — the listener catches all exceptions
    $listener->handle($event);

    // No ActionLog should be created since it failed
    expect(ActionLog::count())->toBe(0);
});

it('stores metadata when provided', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    event(new AuditableEvent(
        model: $user,
        action: 'updated',
        metadata: ['source' => 'admin_panel'],
    ));

    $log = ActionLog::first();
    expect($log->metadata)->toBe(['source' => 'admin_panel']);
});
