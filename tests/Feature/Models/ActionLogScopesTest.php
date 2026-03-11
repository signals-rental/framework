<?php

use App\Models\ActionLog;
use App\Models\User;

describe('scopeForEntity', function () {
    it('filters by auditable type', function () {
        ActionLog::factory()->create(['auditable_type' => 'App\\Models\\User']);
        ActionLog::factory()->create(['auditable_type' => 'App\\Models\\Member']);

        $results = ActionLog::forEntity('App\\Models\\User')->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->auditable_type)->toBe('App\\Models\\User');
    });

    it('filters by auditable type and id', function () {
        ActionLog::factory()->create(['auditable_type' => 'App\\Models\\User', 'auditable_id' => 1]);
        ActionLog::factory()->create(['auditable_type' => 'App\\Models\\User', 'auditable_id' => 2]);

        $results = ActionLog::forEntity('App\\Models\\User', 1)->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->auditable_id)->toBe(1);
    });
});

describe('scopeForUser', function () {
    it('filters by user id', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        ActionLog::factory()->forUser($user)->create();
        ActionLog::factory()->forUser($otherUser)->create();

        $results = ActionLog::forUser($user->id)->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->user_id)->toBe($user->id);
    });
});

describe('scopeForAction', function () {
    it('filters by action name', function () {
        ActionLog::factory()->create(['action' => 'created']);
        ActionLog::factory()->create(['action' => 'updated']);
        ActionLog::factory()->create(['action' => 'deleted']);

        $results = ActionLog::forAction('created')->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->action)->toBe('created');
    });
});

describe('scopeCreatedBetween', function () {
    it('filters by date range', function () {
        ActionLog::factory()->create(['created_at' => now()->subDays(5)]);
        ActionLog::factory()->create(['created_at' => now()->subDay()]);
        ActionLog::factory()->create(['created_at' => now()->addDay()]);

        $results = ActionLog::createdBetween(
            now()->subDays(2)->toDateTimeString(),
            now()->toDateTimeString()
        )->get();

        expect($results)->toHaveCount(1);
    });
});

describe('relationships', function () {
    it('has auditable morph relationship', function () {
        $user = User::factory()->create();
        $log = ActionLog::factory()->create([
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);

        expect($log->auditable)->toBeInstanceOf(User::class);
        /** @var User $auditable */
        $auditable = $log->auditable;
        expect($auditable->id)->toBe($user->id);
    });
});

describe('chained scopes', function () {
    it('combines multiple scopes', function () {
        $user = User::factory()->create();
        ActionLog::factory()->forUser($user)->create([
            'action' => 'created',
            'auditable_type' => 'App\\Models\\User',
            'created_at' => now()->subHour(),
        ]);
        ActionLog::factory()->forUser($user)->create([
            'action' => 'updated',
            'auditable_type' => 'App\\Models\\User',
        ]);
        ActionLog::factory()->create([
            'action' => 'created',
            'auditable_type' => 'App\\Models\\Member',
        ]);

        $results = ActionLog::forUser($user->id)
            ->forAction('created')
            ->forEntity('App\\Models\\User')
            ->get();

        expect($results)->toHaveCount(1);
    });
});
