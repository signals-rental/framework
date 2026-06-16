<?php

use App\Enums\ActivityStatus;
use App\Enums\TimeStatus;
use App\Models\Activity;
use App\Models\User;
use App\Services\Calendar\IcsFeedBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Build an ICS document for a single, eager-loaded activity.
 */
function buildIcsFor(Activity $activity, string $name = 'Test Calendar'): string
{
    $activity->load('owner');

    return (new IcsFeedBuilder)->build(new Collection([$activity]), $name);
}

/**
 * Split an ICS document back into its physical (CRLF-delimited) lines.
 *
 * @return list<string>
 */
function icsLines(string $ics): array
{
    return explode("\r\n", $ics);
}

beforeEach(function () {
    config(['app.url' => 'https://signals.test']);
});

it('wraps events in a VCALENDAR with the required headers and CRLF endings', function () {
    $owner = User::factory()->create();
    $activity = Activity::factory()->for($owner, 'owner')->create([
        'starts_at' => '2026-06-15 09:00:00',
        'ends_at' => '2026-06-15 10:00:00',
    ]);

    $ics = buildIcsFor($activity, 'My Cal');

    expect($ics)->toContain("\r\n")
        ->and($ics)->not->toContain("\n\n");

    $lines = icsLines($ics);

    expect($lines[0])->toBe('BEGIN:VCALENDAR')
        ->and($lines)->toContain('VERSION:2.0')
        ->and($lines)->toContain('PRODID:-//Signals//Calendar//EN')
        ->and($lines)->toContain('CALSCALE:GREGORIAN')
        ->and($lines)->toContain('X-WR-CALNAME:My Cal')
        ->and($lines)->toContain('BEGIN:VEVENT')
        ->and($lines)->toContain('END:VEVENT')
        ->and($lines)->toContain('END:VCALENDAR');
});

it('emits a UID with the activity id and configured host', function () {
    $activity = Activity::factory()->create([
        'starts_at' => '2026-06-15 09:00:00',
        'ends_at' => '2026-06-15 10:00:00',
    ]);

    $ics = buildIcsFor($activity);

    expect(icsLines($ics))->toContain('UID:activity-'.$activity->id.'@signals.test');
});

it('falls back to a signals host when app.url has no parseable host', function () {
    config(['app.url' => 'not-a-url']);

    $activity = Activity::factory()->create([
        'starts_at' => '2026-06-15 09:00:00',
        'ends_at' => '2026-06-15 10:00:00',
    ]);

    $ics = buildIcsFor($activity);

    expect(icsLines($ics))->toContain('UID:activity-'.$activity->id.'@signals');
});

it('formats DTSTART and DTEND in UTC basic format', function () {
    $activity = Activity::factory()->create([
        'starts_at' => '2026-06-15 09:30:00',
        'ends_at' => '2026-06-15 11:15:00',
    ]);

    $lines = icsLines(buildIcsFor($activity));

    expect($lines)->toContain('DTSTART:20260615T093000Z')
        ->and($lines)->toContain('DTEND:20260615T111500Z');
});

it('emits a DTSTAMP in UTC basic format', function () {
    Carbon::setTestNow('2026-06-13 08:00:00');

    $activity = Activity::factory()->create([
        'starts_at' => '2026-06-15 09:00:00',
        'ends_at' => '2026-06-15 10:00:00',
    ]);

    expect(icsLines(buildIcsFor($activity)))->toContain('DTSTAMP:20260613T080000Z');

    Carbon::setTestNow();
});

it('defaults DTEND to DTSTART plus 30 minutes when ends_at is null (D9)', function () {
    $activity = Activity::factory()->create([
        'starts_at' => '2026-06-15 09:00:00',
        'ends_at' => null,
    ]);

    $lines = icsLines(buildIcsFor($activity));

    expect($lines)->toContain('DTSTART:20260615T090000Z')
        ->and($lines)->toContain('DTEND:20260615T093000Z');
});

it('emits VALUE=DATE with an exclusive DTEND for all-day events (D8)', function () {
    $activity = Activity::factory()->create([
        'starts_at' => '2026-06-15 00:00:00',
        'ends_at' => null,
    ]);

    $lines = icsLines(buildIcsFor($activity));

    expect($lines)->toContain('DTSTART;VALUE=DATE:20260615')
        ->and($lines)->toContain('DTEND;VALUE=DATE:20260616')
        ->and($lines)->not->toContain('DTSTART:20260615T000000Z');
});

it('treats a 00:00 to 23:59 event as all-day', function () {
    $activity = Activity::factory()->create([
        'starts_at' => '2026-06-15 00:00:00',
        'ends_at' => '2026-06-15 23:59:00',
    ]);

    $lines = icsLines(buildIcsFor($activity));

    expect($lines)->toContain('DTSTART;VALUE=DATE:20260615')
        ->and($lines)->toContain('DTEND;VALUE=DATE:20260616');
});

it('treats a 00:00 to next-midnight event as all-day', function () {
    $activity = Activity::factory()->create([
        'starts_at' => '2026-06-15 00:00:00',
        'ends_at' => '2026-06-16 00:00:00',
    ]);

    $lines = icsLines(buildIcsFor($activity));

    expect($lines)->toContain('DTSTART;VALUE=DATE:20260615')
        ->and($lines)->toContain('DTEND;VALUE=DATE:20260616');
});

it('spans a multi-day all-day event via the 23:59 heuristic with an exclusive DTEND (I3)', function () {
    $activity = Activity::factory()->create([
        'starts_at' => '2026-06-15 00:00:00',
        'ends_at' => '2026-06-17 23:59:00',
    ]);

    $lines = icsLines(buildIcsFor($activity));

    // Three-day event (15th-17th) → exclusive end is the 18th, not a single day.
    expect($lines)->toContain('DTSTART;VALUE=DATE:20260615')
        ->and($lines)->toContain('DTEND;VALUE=DATE:20260618')
        ->and($lines)->not->toContain('DTEND;VALUE=DATE:20260616');
});

it('spans a multi-day all-day event ending at midnight without an extra day (I3)', function () {
    $activity = Activity::factory()->create([
        'starts_at' => '2026-06-15 00:00:00',
        'ends_at' => '2026-06-18 00:00:00',
    ]);

    $lines = icsLines(buildIcsFor($activity));

    // Three-day midnight→midnight span: midnight end is already exclusive → DTEND
    // is the 18th, not the 19th, and the whole event is VALUE=DATE (all-day).
    expect($lines)->toContain('DTSTART;VALUE=DATE:20260615')
        ->and($lines)->toContain('DTEND;VALUE=DATE:20260618')
        ->and($lines)->not->toContain('DTEND;VALUE=DATE:20260619')
        ->and($lines)->not->toContain('DTSTART:20260615T000000Z');
});

it('emits a timed 09:00 to 17:00 event with UTC date-times, not as all-day', function () {
    settings()->set('scheduling.default_start_time', '09:00');
    settings()->set('scheduling.default_end_time', '17:00');

    $activity = Activity::factory()->create([
        'starts_at' => '2026-06-15 09:00:00',
        'ends_at' => '2026-06-15 17:00:00',
    ]);

    $lines = icsLines(buildIcsFor($activity));

    // A working-hours event is timed, never all-day — emitted as UTC date-times.
    expect($lines)->toContain('DTSTART:20260615T090000Z')
        ->and($lines)->toContain('DTEND:20260615T170000Z')
        ->and($lines)->not->toContain('DTSTART;VALUE=DATE:20260615');
});

it('resolves all-day against the company timezone with the correct local DATE', function () {
    // Asia/Singapore is UTC+8. A local all-day block (00:00 → next 00:00 local) is
    // stored UTC across a day boundary: 2026-06-14 16:00Z → 2026-06-15 16:00Z.
    // Neither endpoint is a UTC midnight, so a UTC-based check would emit a timed
    // event on the wrong day. Converting to local first yields VALUE=DATE 20260615.
    settings()->set('company.timezone', 'Asia/Singapore');

    $activity = Activity::factory()->create([
        'starts_at' => '2026-06-14 16:00:00',
        'ends_at' => '2026-06-15 16:00:00',
    ]);

    $lines = icsLines(buildIcsFor($activity));

    expect($lines)->toContain('DTSTART;VALUE=DATE:20260615')
        ->and($lines)->toContain('DTEND;VALUE=DATE:20260616')
        ->and($lines)->not->toContain('DTSTART;VALUE=DATE:20260614')
        ->and($lines)->not->toContain('DTSTART:20260614T160000Z');
});

describe('STATUS mapping', function () {
    it('maps Scheduled to CONFIRMED', function () {
        $activity = Activity::factory()->create([
            'status_id' => ActivityStatus::Scheduled,
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
        ]);

        $lines = icsLines(buildIcsFor($activity));

        expect($lines)->toContain('STATUS:CONFIRMED')
            ->and($lines)->not->toContain('X-COMPLETED:TRUE');
    });

    it('maps Held to CONFIRMED', function () {
        $activity = Activity::factory()->create([
            'status_id' => ActivityStatus::Held,
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
        ]);

        expect(icsLines(buildIcsFor($activity)))->toContain('STATUS:CONFIRMED');
    });

    it('maps Cancelled to CANCELLED', function () {
        $activity = Activity::factory()->create([
            'status_id' => ActivityStatus::Cancelled,
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
        ]);

        $lines = icsLines(buildIcsFor($activity));

        expect($lines)->toContain('STATUS:CANCELLED')
            ->and($lines)->not->toContain('STATUS:CONFIRMED');
    });

    it('maps Completed to CONFIRMED with an X-COMPLETED line', function () {
        $activity = Activity::factory()->create([
            'status_id' => ActivityStatus::Completed,
            'completed' => true,
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
        ]);

        $lines = icsLines(buildIcsFor($activity));

        expect($lines)->toContain('STATUS:CONFIRMED')
            ->and($lines)->toContain('X-COMPLETED:TRUE');
    });
});

describe('TRANSP mapping', function () {
    it('maps Free time status to TRANSPARENT', function () {
        $activity = Activity::factory()->create([
            'time_status' => TimeStatus::Free,
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
        ]);

        expect(icsLines(buildIcsFor($activity)))->toContain('TRANSP:TRANSPARENT');
    });

    it('maps Busy time status to OPAQUE', function () {
        $activity = Activity::factory()->create([
            'time_status' => TimeStatus::Busy,
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
        ]);

        expect(icsLines(buildIcsFor($activity)))->toContain('TRANSP:OPAQUE');
    });
});

it('includes an ORGANIZER line with the owner name and email', function () {
    $owner = User::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);
    $activity = Activity::factory()->for($owner, 'owner')->create([
        'starts_at' => '2026-06-15 09:00:00',
        'ends_at' => '2026-06-15 10:00:00',
    ]);

    expect(icsLines(buildIcsFor($activity)))
        ->toContain('ORGANIZER;CN="Ada Lovelace":mailto:ada@example.com');
});

it('double-quotes the ORGANIZER CN when the owner name contains a comma', function () {
    $owner = User::factory()->create([
        'name' => 'Lovelace, Ada',
        'email' => 'ada@example.com',
    ]);
    $activity = Activity::factory()->for($owner, 'owner')->create([
        'starts_at' => '2026-06-15 09:00:00',
        'ends_at' => '2026-06-15 10:00:00',
    ]);

    expect(icsLines(buildIcsFor($activity)))
        ->toContain('ORGANIZER;CN="Lovelace, Ada":mailto:ada@example.com');
});

it('omits the ORGANIZER line when the owner is null', function () {
    $activity = Activity::factory()->create([
        'starts_at' => '2026-06-15 09:00:00',
        'ends_at' => '2026-06-15 10:00:00',
    ]);

    // Force the owner relation to resolve to null (e.g. deleted user).
    $activity->setRelation('owner', null);

    $ics = (new IcsFeedBuilder)->build(new Collection([$activity]), 'Test Calendar');

    expect($ics)->not->toContain('ORGANIZER');
});

it('escapes commas, semicolons, backslashes, and newlines in text values', function () {
    $activity = Activity::factory()->create([
        'subject' => 'Lunch, then talk; about C:\\Path',
        'description' => "Line one\nLine two",
        'location' => 'A, B; C',
        'starts_at' => '2026-06-15 09:00:00',
        'ends_at' => '2026-06-15 10:00:00',
    ]);

    $lines = icsLines(buildIcsFor($activity));

    expect($lines)->toContain('SUMMARY:Lunch\, then talk\; about C:\\\\Path')
        ->and($lines)->toContain('LOCATION:A\, B\; C')
        ->and($lines)->toContain('DESCRIPTION:Line one\nLine two');
});

it('omits LOCATION and DESCRIPTION lines when they are blank', function () {
    $activity = Activity::factory()->create([
        'location' => null,
        'description' => null,
        'starts_at' => '2026-06-15 09:00:00',
        'ends_at' => '2026-06-15 10:00:00',
    ]);

    $ics = buildIcsFor($activity);

    expect($ics)->not->toContain('LOCATION:')
        ->and($ics)->not->toContain('DESCRIPTION:');
});

it('folds long lines so each physical line is at most 75 octets', function () {
    $activity = Activity::factory()->create([
        'subject' => str_repeat('A', 200),
        'starts_at' => '2026-06-15 09:00:00',
        'ends_at' => '2026-06-15 10:00:00',
    ]);

    $ics = buildIcsFor($activity);

    foreach (icsLines($ics) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75);
    }

    // Continuation lines begin with a single space.
    expect($ics)->toContain("\r\n ");

    // Unfolding (strip CRLF + leading space) restores the original summary.
    $unfolded = str_replace("\r\n ", '', $ics);
    expect($unfolded)->toContain('SUMMARY:'.str_repeat('A', 200));
});

it('produces a valid empty VCALENDAR for an empty collection', function () {
    $ics = (new IcsFeedBuilder)->build(new Collection, 'Empty Calendar');

    $lines = icsLines($ics);

    expect($lines[0])->toBe('BEGIN:VCALENDAR')
        ->and($lines)->toContain('X-WR-CALNAME:Empty Calendar')
        ->and($lines)->not->toContain('BEGIN:VEVENT')
        ->and($ics)->toContain("END:VCALENDAR\r\n");
});
