<?php

use App\Jobs\ExportActionLog;
use App\Models\ActionLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
});

it('exports action log entries to CSV', function () {
    $user = User::factory()->create();

    ActionLog::create([
        'user_id' => $user->id,
        'action' => 'user.created',
        'auditable_type' => User::class,
        'auditable_id' => $user->id,
        'ip_address' => '127.0.0.1',
    ]);

    (new ExportActionLog($user->id))->handle();

    $cacheKey = "action-log-export:{$user->id}";
    $filename = Cache::get($cacheKey);

    expect($filename)->not->toBeNull();
    Storage::assertExists($filename);

    $content = Storage::get($filename);
    expect($content)->toContain('user.created');
    expect($content)->toContain($user->name);
});

it('applies action filter', function () {
    $user = User::factory()->create();

    ActionLog::create([
        'user_id' => $user->id,
        'action' => 'user.created',
        'auditable_type' => User::class,
        'auditable_id' => $user->id,
    ]);

    ActionLog::create([
        'user_id' => $user->id,
        'action' => 'user.deleted',
        'auditable_type' => User::class,
        'auditable_id' => $user->id,
    ]);

    (new ExportActionLog($user->id, ['action' => 'user.created']))->handle();

    $filename = Cache::get("action-log-export:{$user->id}");
    $content = Storage::get($filename);

    expect($content)->toContain('user.created');
    expect($content)->not->toContain('user.deleted');
});

it('applies auditable_type filter', function () {
    $user = User::factory()->create();

    ActionLog::create([
        'user_id' => $user->id,
        'action' => 'user.created',
        'auditable_type' => User::class,
        'auditable_id' => $user->id,
    ]);

    ActionLog::create([
        'user_id' => $user->id,
        'action' => 'member.created',
        'auditable_type' => 'App\\Models\\Member',
        'auditable_id' => 1,
    ]);

    (new ExportActionLog($user->id, ['auditable_type' => User::class]))->handle();

    $filename = Cache::get("action-log-export:{$user->id}");
    $content = Storage::get($filename);

    expect($content)->toContain('user.created');
    expect($content)->not->toContain('member.created');
});

it('applies date_from and date_to filters', function () {
    $user = User::factory()->create();

    ActionLog::factory()->forUser($user)->create([
        'action' => 'old.action',
        'created_at' => '2024-01-01 12:00:00',
    ]);

    ActionLog::factory()->forUser($user)->create([
        'action' => 'recent.action',
        'created_at' => '2025-06-15 12:00:00',
    ]);

    (new ExportActionLog($user->id, [
        'date_from' => '2025-01-01',
        'date_to' => '2025-12-31',
    ]))->handle();

    $filename = Cache::get("action-log-export:{$user->id}");
    $content = Storage::get($filename);

    expect($content)->toContain('recent.action');
    expect($content)->not->toContain('old.action');
});

it('handles action log entries with no user', function () {
    $user = User::factory()->create();

    ActionLog::create([
        'user_id' => null,
        'action' => 'system.action',
        'auditable_type' => User::class,
        'auditable_id' => $user->id,
    ]);

    (new ExportActionLog($user->id))->handle();

    $filename = Cache::get("action-log-export:{$user->id}");
    $content = Storage::get($filename);

    expect($content)->toContain('system.action')
        ->toContain('System');
});

it('includes CSV header row', function () {
    $user = User::factory()->create();

    (new ExportActionLog($user->id))->handle();

    $filename = Cache::get("action-log-export:{$user->id}");
    $content = Storage::get($filename);

    expect($content)->toContain('ID,Action,"Entity Type","Entity ID",User,"IP Address",Date');
});

it('caches the download URL for one hour', function () {
    $user = User::factory()->create();

    (new ExportActionLog($user->id))->handle();

    $cacheKey = "action-log-export:{$user->id}";
    expect(Cache::has($cacheKey))->toBeTrue();
});
