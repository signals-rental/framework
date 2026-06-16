<?php

use App\Data\Calendar\CalendarEventData;
use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Collection;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

// ── Defaults ──────────────────────────────────────────────────────────────────

it('defaults to today and the week view', function () {
    $user = User::factory()->owner()->create();

    Volt::actingAs($user)
        ->test('calendar.index')
        ->assertSet('view', 'week')
        ->assertSet('startDate', today()->toDateString());
});

it('renders the calendar page', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->get(route('calendar.index'))
        ->assertOk()
        ->assertSee('Calendar');
});

// ── View switching ────────────────────────────────────────────────────────────

it('switches between day, week, and month views', function () {
    $user = User::factory()->owner()->create();

    Volt::actingAs($user)
        ->test('calendar.index')
        ->call('setView', 'day')
        ->assertSet('view', 'day')
        ->call('setView', 'month')
        ->assertSet('view', 'month')
        ->call('setView', 'week')
        ->assertSet('view', 'week');
});

it('ignores an invalid view', function () {
    $user = User::factory()->owner()->create();

    Volt::actingAs($user)
        ->test('calendar.index')
        ->call('setView', 'year')
        ->assertSet('view', 'week');
});

// ── Range computation ─────────────────────────────────────────────────────────

it('computes a single-day range in day view', function () {
    $user = User::factory()->owner()->create();

    Volt::actingAs($user)
        ->test('calendar.index')
        ->set('startDate', '2026-07-15')
        ->call('setView', 'day')
        ->tap(function ($component) {
            /** @var array{0: CarbonInterface, 1: CarbonInterface} $range */
            $range = $component->instance()->range();
            [$from, $to] = $range;

            expect($from->toDateString())->toBe('2026-07-15')
                ->and($to->toDateString())->toBe('2026-07-15');
        });
});

it('computes a seven-day week range honouring the configured week start (Monday)', function () {
    settings()->set('preferences.first_day_of_week', 1);
    $user = User::factory()->owner()->create();

    // 2026-07-15 is a Wednesday.
    Volt::actingAs($user)
        ->test('calendar.index')
        ->set('startDate', '2026-07-15')
        ->tap(function ($component) {
            /** @var array{0: CarbonInterface, 1: CarbonInterface} $range */
            $range = $component->instance()->range();
            [$from, $to] = $range;

            expect($from->dayOfWeek)->toBe(Carbon::MONDAY)
                ->and($from->toDateString())->toBe('2026-07-13')
                ->and($to->toDateString())->toBe('2026-07-19');
        });
});

it('honours preferences.first_day_of_week = 0 (Sunday start)', function () {
    settings()->set('preferences.first_day_of_week', 0);
    $user = User::factory()->owner()->create();

    // 2026-07-15 is a Wednesday; the Sunday-start week begins 2026-07-12.
    Volt::actingAs($user)
        ->test('calendar.index')
        ->set('startDate', '2026-07-15')
        ->tap(function ($component) {
            /** @var array{0: CarbonInterface, 1: CarbonInterface} $range */
            $range = $component->instance()->range();
            [$from, $to] = $range;

            expect($from->dayOfWeek)->toBe(Carbon::SUNDAY)
                ->and($from->toDateString())->toBe('2026-07-12')
                ->and($to->toDateString())->toBe('2026-07-18');
        });
});

it('computes a full-weeks month grid range', function () {
    settings()->set('preferences.first_day_of_week', 1);
    $user = User::factory()->owner()->create();

    Volt::actingAs($user)
        ->test('calendar.index')
        ->set('startDate', '2026-07-15')
        ->call('setView', 'month')
        ->tap(function ($component) {
            /** @var array{0: CarbonInterface, 1: CarbonInterface} $range */
            $range = $component->instance()->range();
            [$from, $to] = $range;

            // July 2026 starts on a Wednesday → the grid starts the preceding Monday.
            expect($from->toDateString())->toBe('2026-06-29')
                ->and($from->dayOfWeek)->toBe(Carbon::MONDAY)
                ->and($to->toDateString())->toBe('2026-08-02');
        });
});

// ── Navigation ────────────────────────────────────────────────────────────────

it('steps forward and back by week and resets to today', function () {
    $user = User::factory()->owner()->create();

    Volt::actingAs($user)
        ->test('calendar.index')
        ->set('startDate', '2026-07-15')
        ->call('next')
        ->assertSet('startDate', '2026-07-22')
        ->call('prev')
        ->assertSet('startDate', '2026-07-15')
        ->call('goToday')
        ->assertSet('startDate', today()->toDateString());
});

it('steps by a single day in day view', function () {
    $user = User::factory()->owner()->create();

    Volt::actingAs($user)
        ->test('calendar.index')
        ->call('setView', 'day')
        ->set('startDate', '2026-07-15')
        ->call('next')
        ->assertSet('startDate', '2026-07-16');
});

it('steps by a month in month view', function () {
    $user = User::factory()->owner()->create();

    Volt::actingAs($user)
        ->test('calendar.index')
        ->call('setView', 'month')
        ->set('startDate', '2026-07-15')
        ->call('next')
        ->assertSet('startDate', '2026-08-15');
});

it('jumps to a day view via openDayView', function () {
    $user = User::factory()->owner()->create();

    Volt::actingAs($user)
        ->test('calendar.index')
        ->call('openDayView', '2026-09-09')
        ->assertSet('view', 'day')
        ->assertSet('startDate', '2026-09-09');
});

// ── Events ────────────────────────────────────────────────────────────────────

it('returns only in-range scheduled activities', function () {
    $user = User::factory()->owner()->create();

    $inRange = Activity::factory()->create([
        'subject' => 'In range',
        'starts_at' => '2026-07-15 10:00',
        'ends_at' => '2026-07-15 11:00',
    ]);
    Activity::factory()->create([
        'subject' => 'Out of range',
        'starts_at' => '2026-09-01 10:00',
        'ends_at' => '2026-09-01 11:00',
    ]);
    Activity::factory()->create(['subject' => 'No start', 'starts_at' => null]);

    Volt::actingAs($user)
        ->test('calendar.index')
        ->call('setView', 'day')
        ->set('startDate', '2026-07-15')
        ->tap(function ($component) use ($inRange) {
            /** @var Collection<int, CalendarEventData> $events */
            $events = $component->instance()->events();

            expect($events)->toHaveCount(1)
                ->and($events->first()->id)->toBe($inRange->id);
        });
});

it('returns unscheduled activities only', function () {
    $user = User::factory()->owner()->create();

    $unscheduled = Activity::factory()->create(['subject' => 'No start', 'starts_at' => null]);
    Activity::factory()->create(['subject' => 'Scheduled', 'starts_at' => '2026-07-15 10:00']);

    Volt::actingAs($user)
        ->test('calendar.index')
        ->tap(function ($component) use ($unscheduled) {
            /** @var Collection<int, CalendarEventData> $events */
            $events = $component->instance()->unscheduledEvents();

            expect($events)->toHaveCount(1)
                ->and($events->first()->id)->toBe($unscheduled->id);
        });
});

it('filters events by owner ids', function () {
    $user = User::factory()->owner()->create();
    $owner = User::factory()->create();
    $other = User::factory()->create();

    Activity::factory()->create([
        'subject' => 'Mine',
        'owned_by' => $owner->id,
        'starts_at' => '2026-07-15 10:00',
    ]);
    Activity::factory()->create([
        'subject' => 'Theirs',
        'owned_by' => $other->id,
        'starts_at' => '2026-07-15 12:00',
    ]);

    Volt::actingAs($user)
        ->test('calendar.index')
        ->call('setView', 'day')
        ->set('startDate', '2026-07-15')
        ->call('toggleOwner', $other->id)
        ->tap(function ($component) use ($owner) {
            /** @var Collection<int, CalendarEventData> $events */
            $events = $component->instance()->events();

            expect($events)->toHaveCount(1)
                ->and($events->first()->owner_id)->toBe($owner->id);
        })
        // clearOwners empties the filter, restoring all events.
        ->call('clearOwners')
        ->tap(function ($component) {
            /** @var Collection<int, CalendarEventData> $events */
            $events = $component->instance()->events();

            expect($events)->toHaveCount(2);
        });
});

it('counts range stats', function () {
    $user = User::factory()->owner()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();

    Activity::factory()->create(['owned_by' => $a->id, 'starts_at' => '2026-07-15 09:00', 'completed' => true, 'status_id' => ActivityStatus::Completed]);
    Activity::factory()->create(['owned_by' => $b->id, 'starts_at' => '2026-07-15 11:00']);

    Volt::actingAs($user)
        ->test('calendar.index')
        ->call('setView', 'day')
        ->set('startDate', '2026-07-15')
        ->tap(function ($component) {
            /** @var array{staff: int, activities: int, completed: int} $stats */
            $stats = $component->instance()->stats();

            expect($stats['staff'])->toBe(3)
                ->and($stats['activities'])->toBe(2)
                ->and($stats['completed'])->toBe(1);
        });
});

it('lists active staff and excludes deactivated users', function () {
    $user = User::factory()->owner()->create();
    User::factory()->create(['name' => 'Active Annie']);
    User::factory()->deactivated()->create(['name' => 'Gone Greg']);

    Volt::actingAs($user)
        ->test('calendar.index')
        ->tap(function ($component) {
            /** @var Collection<int, User> $staff */
            $staff = $component->instance()->staff();
            $names = $staff->pluck('name');

            expect($names)->toContain('Active Annie')
                ->and($names)->not->toContain('Gone Greg');
        });
});

// ── Settings-driven hour window ───────────────────────────────────────────────

it('honours the configured working-hours window', function () {
    settings()->set('scheduling.default_start_time', '08:00');
    settings()->set('scheduling.default_end_time', '16:00');
    $user = User::factory()->owner()->create();

    Volt::actingAs($user)
        ->test('calendar.index')
        ->call('setView', 'day')
        ->set('startDate', '2026-07-15')
        ->tap(function ($component) {
            /** @var array{0: int, 1: int} $window */
            $window = $component->instance()->hourWindow();
            [$start, $end] = $window;

            expect($start)->toBe(8)->and($end)->toBe(16);
        });
});

it('auto-expands the hour window to include early or late events', function () {
    settings()->set('scheduling.default_start_time', '09:00');
    settings()->set('scheduling.default_end_time', '17:00');
    $user = User::factory()->owner()->create();

    Activity::factory()->create([
        'starts_at' => '2026-07-15 06:30',
        'ends_at' => '2026-07-15 07:30',
    ]);
    Activity::factory()->create([
        'starts_at' => '2026-07-15 20:00',
        'ends_at' => '2026-07-15 21:15',
    ]);

    Volt::actingAs($user)
        ->test('calendar.index')
        ->call('setView', 'day')
        ->set('startDate', '2026-07-15')
        ->tap(function ($component) {
            /** @var array{0: int, 1: int} $window */
            $window = $component->instance()->hourWindow();
            [$start, $end] = $window;

            expect($start)->toBe(6)->and($end)->toBe(22);
        });
});

it('reflects weekend availability for shading', function () {
    settings()->set('scheduling.weekend_availability', false);
    $user = User::factory()->owner()->create();

    Volt::actingAs($user)
        ->test('calendar.index')
        ->tap(function ($component) {
            expect($component->instance()->weekendShaded())->toBeTrue();
        });

    settings()->set('scheduling.weekend_availability', true);

    Volt::actingAs($user)
        ->test('calendar.index')
        ->tap(function ($component) {
            expect($component->instance()->weekendShaded())->toBeFalse();
        });
});

// ── Realtime refresh ──────────────────────────────────────────────────────────

it('re-queries events when calendar-refresh fires', function () {
    $user = User::factory()->owner()->create();

    $component = Volt::actingAs($user)
        ->test('calendar.index')
        ->call('setView', 'day')
        ->set('startDate', '2026-07-15');

    $component->tap(function ($component) {
        /** @var Collection<int, CalendarEventData> $events */
        $events = $component->instance()->events();
        expect($events)->toHaveCount(0);
    });

    Activity::factory()->create(['starts_at' => '2026-07-15 10:00']);

    $component->dispatch('calendar-refresh')
        ->tap(function ($component) {
            /** @var Collection<int, CalendarEventData> $events */
            $events = $component->instance()->events();
            expect($events)->toHaveCount(1);
        });
});

// ── Authorization ─────────────────────────────────────────────────────────────

it('forbids users without activities.access', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('calendar.index'))
        ->assertForbidden();
});

// ── Multi-day timezone-boundary spanning (regression) ───────────────────────────
//
// Timestamps are stored UTC and rendered in the company timezone. Multi-day timed
// events must render on every LOCAL day they cover — not just their start day. The
// day/week/month grids compare local calendar dates as Y-m-d strings (timezone-
// robust); a previous instant comparison dropped events from their continuation/end
// columns whenever the display timezone differed from UTC. A non-UTC display tz is
// therefore mandatory to guard the regression — these tests use Asia/Singapore (UTC+8,
// no DST). With that tz the activity below spans local 2026-06-14 → 2026-06-15:
//   starts_at 2026-06-14 02:00 UTC → local 2026-06-14 10:00
//   ends_at   2026-06-15 02:00 UTC → local 2026-06-15 10:00

it('renders a multi-day event on its continuation day in day view (timezone boundary)', function () {
    settings()->set('company.timezone', 'Asia/Singapore');
    $user = User::factory()->owner()->create(['timezone' => null]);

    $activity = Activity::factory()->create([
        'subject' => 'Spanning Day Event',
        'owned_by' => $user->id,
        'starts_at' => '2026-06-14 02:00:00',
        'ends_at' => '2026-06-15 02:00:00',
    ]);

    // 2026-06-15 (local) is the continuation/end day — under the old instant
    // comparison the event was dropped from this column.
    Volt::actingAs($user)
        ->test('calendar.index')
        ->call('load')
        ->call('setView', 'day')
        ->set('startDate', '2026-06-15')
        ->assertSee('Spanning Day Event')
        ->assertSeeHtml('day-event-'.$activity->id.'-'.$user->id);
});

it('renders a multi-day event on every covered column in week view (start AND continuation)', function () {
    settings()->set('company.timezone', 'Asia/Singapore');
    // Sunday-start week so local 2026-06-14 (Sun) and 2026-06-15 (Mon) share one week.
    settings()->set('preferences.first_day_of_week', 0);
    $user = User::factory()->owner()->create(['timezone' => null]);

    $activity = Activity::factory()->create([
        'subject' => 'Spanning Week Event',
        'owned_by' => $user->id,
        'starts_at' => '2026-06-14 02:00:00',
        'ends_at' => '2026-06-15 02:00:00',
    ]);

    // startDate is the Sunday week-start; the week covers local 2026-06-14..2026-06-20.
    Volt::actingAs($user)
        ->test('calendar.index')
        ->call('load')
        ->set('startDate', '2026-06-14')
        ->assertSeeHtml('week-event-'.$activity->id.'-2026-06-14')
        ->assertSeeHtml('week-event-'.$activity->id.'-2026-06-15');
});

it('spans a multi-day event across month cells and restores the overflow badge', function () {
    settings()->set('company.timezone', 'Asia/Singapore');
    $user = User::factory()->owner()->create(['timezone' => null]);

    $activity = Activity::factory()->create([
        'subject' => 'Spanning Month Event',
        'owned_by' => $user->id,
        'starts_at' => '2026-06-14 02:00:00', // local 06-14 10:00, continues into 06-15
        'ends_at' => '2026-06-15 02:00:00',   // local 06-15 10:00
    ]);

    // Five additional single-day activities landing on local 2026-06-15 (UTC 04:00 →
    // local 12:00). With the spanning event that is six events on the 2026-06-15 cell;
    // the cell shows three chips then a "+3 more" overflow badge.
    $singleDayCount = 5;
    Activity::factory()->count($singleDayCount)->create([
        'owned_by' => $user->id,
        'starts_at' => '2026-06-15 04:00:00',
        'ends_at' => '2026-06-15 05:00:00',
    ]);

    $overflow = ($singleDayCount + 1) - 3;

    // The spanning event starts local 10:00 (before the 12:00 singles), so it sorts
    // into the visible top-three chips of the continuation cell.
    Volt::actingAs($user)
        ->test('calendar.index')
        ->call('load')
        ->call('setView', 'month')
        ->set('startDate', '2026-06-15')
        ->assertSee('Spanning Month Event')
        ->assertSeeHtml('month-chip-'.$activity->id)
        ->assertSee('+'.$overflow.' more');
});

// ── Unified all-day band (todo #208 / finding PR-1) ─────────────────────────────
//
// The week/day grids previously placed an event in the "All day" band when it
// exactly spanned the configured working hours. That is now driven solely by the
// shared all-day detector (CalendarEventData::$all_day), so a midnight-aligned
// event bands and a 09:00–17:00 working-hours event does not.

it('renders an all-day event in the week all-day band, not the timed grid', function () {
    settings()->set('preferences.first_day_of_week', 0);
    $user = User::factory()->owner()->create(['timezone' => null]);

    $activity = Activity::factory()->create([
        'subject' => 'All Day Block',
        'owned_by' => $user->id,
        'starts_at' => '2026-06-15 00:00:00',
        'ends_at' => '2026-06-16 00:00:00',
    ]);

    Volt::actingAs($user)
        ->test('calendar.index')
        ->call('load')
        ->set('startDate', '2026-06-14')
        ->assertSeeHtml('week-band-'.$activity->id)
        ->assertDontSeeHtml('week-event-'.$activity->id.'-2026-06-15');
});

it('bands a 3-day midnight-to-midnight event across its week columns', function () {
    settings()->set('preferences.first_day_of_week', 0);
    $user = User::factory()->owner()->create(['timezone' => null]);

    $activity = Activity::factory()->create([
        'subject' => 'Three Day Block',
        'owned_by' => $user->id,
        'starts_at' => '2026-06-15 00:00:00',
        'ends_at' => '2026-06-18 00:00:00',
    ]);

    Volt::actingAs($user)
        ->test('calendar.index')
        ->call('load')
        ->set('startDate', '2026-06-14')
        ->assertSeeHtml('week-band-'.$activity->id)
        ->assertSee('Three Day Block');
});

it('keeps a 09:00 to 17:00 working-hours event in the timed grid, not the all-day band', function () {
    settings()->set('preferences.first_day_of_week', 0);
    settings()->set('scheduling.default_start_time', '09:00');
    settings()->set('scheduling.default_end_time', '17:00');
    $user = User::factory()->owner()->create(['timezone' => null]);

    $activity = Activity::factory()->create([
        'subject' => 'Working Hours Meeting',
        'owned_by' => $user->id,
        'starts_at' => '2026-06-15 09:00:00',
        'ends_at' => '2026-06-15 17:00:00',
    ]);

    Volt::actingAs($user)
        ->test('calendar.index')
        ->call('load')
        ->set('startDate', '2026-06-14')
        ->assertSeeHtml('week-event-'.$activity->id.'-2026-06-15')
        ->assertDontSeeHtml('week-band-'.$activity->id);
});

it('renders an all-day event in the day-view all-day band, not the timed grid', function () {
    $user = User::factory()->owner()->create(['timezone' => null]);

    $activity = Activity::factory()->create([
        'subject' => 'Day All Day Block',
        'owned_by' => $user->id,
        'starts_at' => '2026-06-15 00:00:00',
        'ends_at' => '2026-06-16 00:00:00',
    ]);

    Volt::actingAs($user)
        ->test('calendar.index')
        ->call('load')
        ->call('setView', 'day')
        ->set('startDate', '2026-06-15')
        ->assertSeeHtml('allday-'.$activity->id)
        ->assertDontSeeHtml('day-event-'.$activity->id.'-'.$user->id);
});
