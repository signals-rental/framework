<?php

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\User;
use App\Services\Calendar\CalendarEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new CalendarEventService;
    $this->from = Carbon::parse('2026-06-15 00:00:00');
    $this->to = Carbon::parse('2026-06-15 23:59:59');
});

describe('scheduled()', function () {
    it('returns activities overlapping the range', function () {
        $inside = Activity::factory()->create([
            'starts_at' => '2026-06-15 10:00:00',
            'ends_at' => '2026-06-15 11:00:00',
        ]);
        $spanning = Activity::factory()->create([
            'starts_at' => '2026-06-14 23:00:00',
            'ends_at' => '2026-06-15 01:00:00',
        ]);
        $before = Activity::factory()->create([
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
        ]);
        $after = Activity::factory()->create([
            'starts_at' => '2026-06-20 10:00:00',
            'ends_at' => '2026-06-20 11:00:00',
        ]);

        $result = $this->service->scheduled($this->from, $this->to);
        $ids = $result->pluck('id');

        expect($ids)->toContain($inside->id, $spanning->id);
        expect($ids)->not->toContain($before->id, $after->id);
    });

    it('includes activities with a null ends_at that start within range', function () {
        $activity = Activity::factory()->create([
            'starts_at' => '2026-06-15 10:00:00',
            'ends_at' => null,
        ]);

        $result = $this->service->scheduled($this->from, $this->to);

        expect($result->pluck('id'))->toContain($activity->id);
    });

    it('excludes an event whose ends_at falls before the range start', function () {
        // ends_at strictly before $from must be excluded (the rule is ends_at >= $from).
        $activity = Activity::factory()->create([
            'starts_at' => '2026-06-14 22:00:00',
            'ends_at' => '2026-06-14 23:00:00',
        ]);

        $result = $this->service->scheduled($this->from, $this->to);

        expect($result->pluck('id'))->not->toContain($activity->id);
    });

    it('includes an event ending exactly at the range start (boundary)', function () {
        $activity = Activity::factory()->create([
            'starts_at' => '2026-06-14 23:00:00',
            'ends_at' => $this->from,
        ]);

        $result = $this->service->scheduled($this->from, $this->to);

        expect($result->pluck('id'))->toContain($activity->id);
    });

    it('excludes activities with a null starts_at', function () {
        $activity = Activity::factory()->create(['starts_at' => null]);

        $result = $this->service->scheduled($this->from, $this->to);

        expect($result->pluck('id'))->not->toContain($activity->id);
    });

    it('filters by owner ids when provided', function () {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $mine = Activity::factory()->for($owner, 'owner')->create([
            'starts_at' => '2026-06-15 10:00:00',
            'ends_at' => '2026-06-15 11:00:00',
        ]);
        $theirs = Activity::factory()->for($other, 'owner')->create([
            'starts_at' => '2026-06-15 12:00:00',
            'ends_at' => '2026-06-15 13:00:00',
        ]);

        $result = $this->service->scheduled($this->from, $this->to, [$owner->id]);
        $ids = $result->pluck('id');

        expect($ids)->toContain($mine->id);
        expect($ids)->not->toContain($theirs->id);
    });

    it('eager-loads the owner and orders by starts_at', function () {
        $second = Activity::factory()->create([
            'starts_at' => '2026-06-15 15:00:00',
            'ends_at' => '2026-06-15 16:00:00',
        ]);
        $first = Activity::factory()->create([
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
        ]);

        $result = $this->service->scheduled($this->from, $this->to);

        expect($result->first()->id)->toBe($first->id)
            ->and($result->last()->id)->toBe($second->id)
            ->and($result->first()->relationLoaded('owner'))->toBeTrue();
    });
});

describe('unscheduled()', function () {
    it('returns only activities with a null starts_at', function () {
        $unscheduled = Activity::factory()->create(['starts_at' => null]);
        $scheduled = Activity::factory()->create(['starts_at' => '2026-06-15 10:00:00']);

        $result = $this->service->unscheduled();
        $ids = $result->pluck('id');

        expect($ids)->toContain($unscheduled->id);
        expect($ids)->not->toContain($scheduled->id);
    });

    it('filters unscheduled by owner ids', function () {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $mine = Activity::factory()->for($owner, 'owner')->create(['starts_at' => null]);
        $theirs = Activity::factory()->for($other, 'owner')->create(['starts_at' => null]);

        $result = $this->service->unscheduled([$owner->id]);
        $ids = $result->pluck('id');

        expect($ids)->toContain($mine->id);
        expect($ids)->not->toContain($theirs->id);
    });
});

describe('forFeed()', function () {
    it('respects the one-year lookback window with no forward cap', function () {
        $recent = Activity::factory()->create(['starts_at' => now()->subMonth()]);
        $future = Activity::factory()->create(['starts_at' => now()->addYears(2)]);
        $old = Activity::factory()->create(['starts_at' => now()->subYears(2)]);
        $null = Activity::factory()->create(['starts_at' => null]);

        $result = $this->service->forFeed();
        $ids = $result->pluck('id');

        expect($ids)->toContain($recent->id, $future->id);
        expect($ids)->not->toContain($old->id, $null->id);
    });

    it('scopes the feed to a single owner', function () {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $mine = Activity::factory()->for($owner, 'owner')->create(['starts_at' => now()->addDay()]);
        $theirs = Activity::factory()->for($other, 'owner')->create(['starts_at' => now()->addDay()]);

        $result = $this->service->forFeed($owner->id);
        $ids = $result->pluck('id');

        expect($ids)->toContain($mine->id);
        expect($ids)->not->toContain($theirs->id);
    });
});

describe('rangeStats()', function () {
    it('counts distinct staff, total activities, and completed', function () {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        // Two activities for one owner, one for another → 2 distinct staff, 3 total.
        Activity::factory()->for($owner, 'owner')->create([
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
            'completed' => true,
            'status_id' => ActivityStatus::Completed,
        ]);
        Activity::factory()->for($owner, 'owner')->create([
            'starts_at' => '2026-06-15 11:00:00',
            'ends_at' => '2026-06-15 12:00:00',
            'completed' => false,
        ]);
        Activity::factory()->for($other, 'owner')->create([
            'starts_at' => '2026-06-15 13:00:00',
            'ends_at' => '2026-06-15 14:00:00',
            'completed' => true,
            'status_id' => ActivityStatus::Completed,
        ]);

        // An out-of-range activity must not be counted.
        Activity::factory()->create([
            'starts_at' => '2026-06-20 09:00:00',
            'ends_at' => '2026-06-20 10:00:00',
            'completed' => true,
            'status_id' => ActivityStatus::Completed,
        ]);

        $stats = $this->service->rangeStats($this->from, $this->to);

        expect($stats)->toBe([
            'staff' => 2,
            'activities' => 3,
            'completed' => 2,
        ]);
    });

    it('honours the owner filter', function () {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Activity::factory()->for($owner, 'owner')->create([
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
            'completed' => true,
            'status_id' => ActivityStatus::Completed,
        ]);
        Activity::factory()->for($other, 'owner')->create([
            'starts_at' => '2026-06-15 11:00:00',
            'ends_at' => '2026-06-15 12:00:00',
            'completed' => true,
            'status_id' => ActivityStatus::Completed,
        ]);

        $stats = $this->service->rangeStats($this->from, $this->to, [$owner->id]);

        expect($stats)->toBe([
            'staff' => 1,
            'activities' => 1,
            'completed' => 1,
        ]);
    });
});
