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

it('caches the download URL for one hour', function () {
    $user = User::factory()->create();

    (new ExportActionLog($user->id))->handle();

    $cacheKey = "action-log-export:{$user->id}";
    expect(Cache::has($cacheKey))->toBeTrue();
});
