<?php

use App\Data\Calendar\CalendarEventData;
use App\Enums\ActivityStatus;
use App\Enums\TimeStatus;
use App\Models\Activity;
use App\Models\ListValue;
use App\Models\Member;
use App\Models\User;
use App\Services\Calendar\OwnerColorResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('maps activity fields, type list value, and owner display hints', function () {
    $owner = User::factory()->create(['name' => 'Ada Lovelace']);
    $member = Member::factory()->create();

    $activity = Activity::factory()
        ->meeting()
        ->for($owner, 'owner')
        ->create([
            'subject' => 'Quarterly review',
            'status_id' => ActivityStatus::Scheduled,
            'time_status' => TimeStatus::Busy,
            'location' => 'Room 4',
            'completed' => false,
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
            'regarding_type' => Member::class,
            'regarding_id' => $member->id,
        ]);

    $meetingValue = ListValue::find($activity->type_id);

    $activity->load(['owner', 'type']);

    $data = CalendarEventData::fromModel($activity);

    expect($data->id)->toBe($activity->id)
        ->and($data->subject)->toBe('Quarterly review')
        ->and($data->owner_id)->toBe($owner->id)
        ->and($data->owner_name)->toBe('Ada Lovelace')
        ->and($data->owner_initials)->toBe('AL')
        ->and($data->owner_color)->toBe((new OwnerColorResolver)->for($owner->id))
        ->and($data->type_id)->toBe($meetingValue->id)
        ->and($data->type_name)->toBe('Meeting')
        ->and($data->type_icon)->toBe('meeting')
        ->and($data->status_id)->toBe(ActivityStatus::Scheduled->value)
        ->and($data->status_name)->toBe('Scheduled')
        ->and($data->time_status)->toBe(TimeStatus::Busy->value)
        ->and($data->location)->toBe('Room 4')
        ->and($data->completed)->toBeFalse()
        ->and($data->regarding_type)->toBe('Member')
        ->and($data->regarding_id)->toBe($member->id)
        ->and($data->all_day)->toBeFalse();
});

it('emits starts_at and ends_at as ISO 8601 strings', function () {
    $activity = Activity::factory()->create([
        'starts_at' => '2026-06-15 09:00:00',
        'ends_at' => '2026-06-15 10:30:00',
    ]);
    $activity->load('owner');

    $data = CalendarEventData::fromModel($activity);

    expect($data->starts_at)->toBe(Carbon::parse('2026-06-15 09:00:00')->toIso8601String())
        ->and($data->ends_at)->toBe(Carbon::parse('2026-06-15 10:30:00')->toIso8601String());
});

it('degrades gracefully when the owner relation is not loaded', function () {
    $activity = Activity::factory()->create();

    // Intentionally not loading the owner relation.
    $data = CalendarEventData::fromModel($activity);

    expect($data->owner_name)->toBe('')
        ->and($data->owner_initials)->toBe('')
        ->and($data->owner_color)->toBe((new OwnerColorResolver)->for($activity->owned_by));
});

it('keeps the raw ends_at without applying the D9 default', function () {
    $activity = Activity::factory()->create([
        'starts_at' => '2026-06-15 09:00:00',
        'ends_at' => null,
    ]);
    $activity->load('owner');

    $data = CalendarEventData::fromModel($activity);

    expect($data->ends_at)->toBeNull();
});

describe('all_day heuristic (D8)', function () {
    it('is true for 00:00 start with a null end', function () {
        $activity = Activity::factory()->create([
            'starts_at' => '2026-06-15 00:00:00',
            'ends_at' => null,
        ]);
        $activity->load('owner');

        expect(CalendarEventData::fromModel($activity)->all_day)->toBeTrue();
    });

    it('is true for 00:00 start ending at 23:59', function () {
        $activity = Activity::factory()->create([
            'starts_at' => '2026-06-15 00:00:00',
            'ends_at' => '2026-06-15 23:59:00',
        ]);
        $activity->load('owner');

        expect(CalendarEventData::fromModel($activity)->all_day)->toBeTrue();
    });

    it('is true for 00:00 start ending at the next midnight', function () {
        $activity = Activity::factory()->create([
            'starts_at' => '2026-06-15 00:00:00',
            'ends_at' => '2026-06-16 00:00:00',
        ]);
        $activity->load('owner');

        expect(CalendarEventData::fromModel($activity)->all_day)->toBeTrue();
    });

    it('is false for a timed 09:00 to 10:00 event', function () {
        $activity = Activity::factory()->create([
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
        ]);
        $activity->load('owner');

        expect(CalendarEventData::fromModel($activity)->all_day)->toBeFalse();
    });

    it('is false when starts_at is null', function () {
        $activity = Activity::factory()->create([
            'starts_at' => null,
            'ends_at' => null,
        ]);
        $activity->load('owner');

        expect(CalendarEventData::fromModel($activity)->all_day)->toBeFalse();
    });
});

describe('regarding name (U8)', function () {
    it('exposes the regarding entity name when loaded', function () {
        $member = Member::factory()->create(['name' => 'Acme Ltd']);
        $activity = Activity::factory()->create([
            'regarding_type' => Member::class,
            'regarding_id' => $member->id,
        ]);
        $activity->load(['owner', 'regarding']);

        expect(CalendarEventData::fromModel($activity)->regarding_name)->toBe('Acme Ltd');
    });

    it('is null when there is no regarding entity', function () {
        $activity = Activity::factory()->create([
            'regarding_type' => null,
            'regarding_id' => null,
        ]);
        $activity->load(['owner', 'regarding']);

        expect(CalendarEventData::fromModel($activity)->regarding_name)->toBeNull();
    });
});

describe('multi-day flag (U9)', function () {
    it('is true when start and end fall on different days', function () {
        $activity = Activity::factory()->create([
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-16 10:00:00',
        ]);
        $activity->load('owner');

        expect(CalendarEventData::fromModel($activity)->is_multi_day)->toBeTrue();
    });

    it('is false for a same-day timed event', function () {
        $activity = Activity::factory()->create([
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 17:00:00',
        ]);
        $activity->load('owner');

        expect(CalendarEventData::fromModel($activity)->is_multi_day)->toBeFalse();
    });

    it('is false for an all-day event', function () {
        $activity = Activity::factory()->create([
            'starts_at' => '2026-06-15 00:00:00',
            'ends_at' => '2026-06-15 23:59:00',
        ]);
        $activity->load('owner');

        expect(CalendarEventData::fromModel($activity)->is_multi_day)->toBeFalse();
    });
});

describe('participants (resolved to staff identity)', function () {
    it('resolves a participant to the linked staff user name and id', function () {
        $member = Member::factory()->create(['name' => 'Linked Member Name']);
        $user = User::factory()->create(['name' => 'Staff Person', 'member_id' => $member->id]);
        $activity = Activity::factory()->create();
        $activity->participants()->create(['member_id' => $member->id]);
        $activity->load(['owner', 'participants.member.user']);

        $participants = CalendarEventData::fromModel($activity)->participants;

        expect($participants)->toHaveCount(1)
            ->and($participants[0]['user_id'])->toBe($user->id)
            ->and($participants[0]['member_id'])->toBe($member->id)
            ->and($participants[0]['name'])->toBe('Staff Person');
    });

    it('falls back to the member name when the participant is not a user', function () {
        $member = Member::factory()->create(['name' => 'Client Co']);
        $activity = Activity::factory()->create();
        $activity->participants()->create(['member_id' => $member->id]);
        $activity->load(['owner', 'participants.member.user']);

        $participants = CalendarEventData::fromModel($activity)->participants;

        expect($participants[0]['user_id'])->toBeNull()
            ->and($participants[0]['name'])->toBe('Client Co');
    });
});
