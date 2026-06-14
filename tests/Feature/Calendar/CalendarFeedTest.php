<?php

use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

it('serves the global feed over a valid signed URL', function () {
    $activity = Activity::factory()->create([
        'subject' => 'Board Meeting',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    $url = URL::signedRoute('calendar.feed.global');

    $response = $this->get($url);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/calendar');
    $response->assertSee('BEGIN:VCALENDAR', false);
    $response->assertSee('Board Meeting', false);
});

it('serves the per-user feed over a valid signed URL', function () {
    $user = User::factory()->create();
    Activity::factory()->for($user, 'owner')->create([
        'subject' => 'One on one',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    $url = URL::signedRoute('calendar.feed.user', ['user' => $user->id]);

    $response = $this->get($url);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/calendar');
    $response->assertSee('One on one', false);
});

it('rejects the global feed without a signature', function () {
    $this->get(route('calendar.feed.global'))->assertForbidden();
});

it('rejects the per-user feed without a signature', function () {
    $user = User::factory()->create();

    $this->get(route('calendar.feed.user', ['user' => $user->id]))->assertForbidden();
});

it('rejects a tampered signature', function () {
    $url = URL::signedRoute('calendar.feed.global').'&extra=tampered';

    $this->get($url)->assertForbidden();
});

it('isolates per-user feeds to that owner only', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Activity::factory()->for($alice, 'owner')->create([
        'subject' => 'Alice Sync',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);
    Activity::factory()->for($bob, 'owner')->create([
        'subject' => 'Bob Sync',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    $url = URL::signedRoute('calendar.feed.user', ['user' => $alice->id]);

    $response = $this->get($url);

    $response->assertOk();
    $response->assertSee('Alice Sync', false);
    $response->assertDontSee('Bob Sync', false);
});

it('honours the feed window: excludes stale activities and includes far-future ones', function () {
    $stale = Activity::factory()->create([
        'subject' => 'Ancient History',
        'starts_at' => now()->subYears(2),
        'ends_at' => now()->subYears(2)->addHour(),
    ]);
    $future = Activity::factory()->create([
        'subject' => 'Distant Future',
        'starts_at' => now()->addYears(3),
        'ends_at' => now()->addYears(3)->addHour(),
    ]);

    $url = URL::signedRoute('calendar.feed.global');

    $response = $this->get($url);

    $response->assertOk();
    $response->assertSee('Distant Future', false);
    $response->assertDontSee('Ancient History', false);
    expect($stale->id)->not->toBe($future->id);
});

it('serves the feed to an unauthenticated client with a valid signature', function () {
    Activity::factory()->create([
        'subject' => 'Public Subscribed Event',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    // No actingAs(): the signed URL is the only credential.
    $url = URL::signedRoute('calendar.feed.global');

    $this->get($url)
        ->assertOk()
        ->assertSee('Public Subscribed Event', false);
});
