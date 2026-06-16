<?php

use App\Data\Activities\ActivityData;
use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\Member;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

// ── Form modal — create ───────────────────────────────────────────────────────

it('creates an activity from the form modal via CreateActivity', function () {
    $user = User::factory()->owner()->create();

    Volt::actingAs($user)
        ->test('calendar.activity-form-modal')
        ->call('open', owned_by: $user->id, starts_at: '2026-07-01 10:00')
        ->assertSet('ownedBy', $user->id)
        ->assertSet('startsAt', '2026-07-01 10:00')
        ->set('subject', 'New from calendar')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('calendar-refresh')
        ->assertDispatched('activity-saved');

    $activity = Activity::query()->where('subject', 'New from calendar')->firstOrFail();

    expect($activity->owned_by)->toBe($user->id);
    expect(Carbon::parse($activity->starts_at)->format('Y-m-d H:i'))->toBe('2026-07-01 10:00');
});

it('validates that subject is required', function () {
    $user = User::factory()->owner()->create();

    Volt::actingAs($user)
        ->test('calendar.activity-form-modal')
        ->call('open')
        ->set('subject', '')
        ->call('save')
        ->assertHasErrors(['subject' => 'required']);

    expect(Activity::query()->count())->toBe(0);
});

// ── Form modal — edit ─────────────────────────────────────────────────────────

it('updates an existing activity from the form modal via UpdateActivity', function () {
    $user = User::factory()->owner()->create();
    $activity = Activity::factory()->create(['subject' => 'Original']);

    Volt::actingAs($user)
        ->test('calendar.activity-form-modal')
        ->call('open', activityId: $activity->id)
        ->assertSet('activityId', $activity->id)
        ->assertSet('subject', 'Original')
        ->set('subject', 'Changed')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('calendar-refresh');

    expect($activity->fresh()->subject)->toBe('Changed');
});

// ── Form modal — slot/date prefill ────────────────────────────────────────────

it('prefills owner and start time from a slot click payload', function () {
    $user = User::factory()->owner()->create();
    $owner = User::factory()->create();

    Volt::actingAs($user)
        ->test('calendar.activity-form-modal')
        ->call('open', owned_by: $owner->id, starts_at: '2026-08-15 13:30')
        ->assertSet('ownedBy', $owner->id)
        ->assertSet('startsAt', '2026-08-15 13:30')
        ->assertSet('activityId', null);
});

it('prefills the working-hours window from a month-day click payload', function () {
    $user = User::factory()->owner()->create();

    Volt::actingAs($user)
        ->test('calendar.activity-form-modal')
        ->call('open', date: '2026-09-20')
        ->assertSet('startsAt', '2026-09-20 09:00')
        ->assertSet('endsAt', '2026-09-20 17:00')
        ->assertSet('ownedBy', $user->id);
});

// ── Form modal — participants ─────────────────────────────────────────────────

it('attaches participants but never the owner', function () {
    $ownerMember = Member::factory()->create();
    $owner = User::factory()->owner()->create(['member_id' => $ownerMember->id]);
    $participant = Member::factory()->create();

    Volt::actingAs($owner)
        ->test('calendar.activity-form-modal')
        ->call('open')
        ->set('subject', 'With participants')
        ->set('ownedBy', $owner->id)
        ->call('toggleParticipant', $participant->id)
        ->call('toggleParticipant', $ownerMember->id)
        ->call('save')
        ->assertHasNoErrors();

    $activity = Activity::query()->where('subject', 'With participants')->firstOrFail();
    $memberIds = $activity->participants()->pluck('member_id')->map(fn ($id) => (int) $id)->all();

    expect($memberIds)->toContain($participant->id)
        ->and($memberIds)->not->toContain($ownerMember->id);
});

// ── Detail modal ──────────────────────────────────────────────────────────────

it('loads an activity into the detail modal', function () {
    $user = User::factory()->owner()->create();
    $activity = Activity::factory()->create(['subject' => 'Inspect rig']);

    Volt::actingAs($user)
        ->test('calendar.activity-detail-modal')
        ->call('open', activityId: $activity->id)
        ->assertSet('activityId', $activity->id)
        ->assertSee('Inspect rig');
});

it('completes an activity from the detail modal and refreshes the calendar', function () {
    $user = User::factory()->owner()->create();
    $activity = Activity::factory()->create();

    Volt::actingAs($user)
        ->test('calendar.activity-detail-modal')
        ->call('open', activityId: $activity->id)
        ->call('complete')
        ->assertDispatched('calendar-refresh')
        ->assertDispatched('activity-detail-done');

    $activity->refresh();

    expect($activity->completed)->toBeTrue()
        ->and($activity->status_id)->toBe(ActivityStatus::Completed);
});

it('deletes an activity from the detail modal and refreshes the calendar', function () {
    $user = User::factory()->owner()->create();
    $activity = Activity::factory()->create();

    Volt::actingAs($user)
        ->test('calendar.activity-detail-modal')
        ->call('open', activityId: $activity->id)
        ->call('delete')
        ->assertDispatched('calendar-refresh')
        ->assertDispatched('activity-detail-done');

    expect(Activity::query()->find($activity->id))->toBeNull();
});

// ── Detail modal — owner avatar (DTO owner_thumb_url) ──────────────────────────

it('renders the owner avatar photo from the DTO owner_thumb_url (OSS-10)', function () {
    Storage::fake('public');

    $viewer = User::factory()->owner()->create();
    $ownerMember = Member::factory()->create(['icon_thumb_url' => 'members/icons/owner-thumb.png']);
    $owner = User::factory()->create(['name' => 'Owen Owner', 'member_id' => $ownerMember->id]);
    $activity = Activity::factory()->create(['owned_by' => $owner->id]);

    Volt::actingAs($viewer)
        ->test('calendar.activity-detail-modal')
        ->call('open', activityId: $activity->id)
        ->assertSee('Owen Owner')
        ->assertSeeHtml('<img');
});

it('exposes a signed owner_thumb_url on ActivityData when the owner has a thumbnail', function () {
    Storage::fake('public');

    $ownerMember = Member::factory()->create(['icon_thumb_url' => 'members/icons/owner-thumb.png']);
    $owner = User::factory()->create(['member_id' => $ownerMember->id]);
    $activity = Activity::factory()->create(['owned_by' => $owner->id]);

    $data = ActivityData::fromModel(
        $activity->load(['owner.member', 'type'])
    );

    expect($data->owner_thumb_url)->toBeString();
    expect($data->owner_thumb_url)->not->toBeEmpty();
});

it('leaves owner_thumb_url null when the owner has no thumbnail', function () {
    $ownerMember = Member::factory()->create(['icon_thumb_url' => null]);
    $owner = User::factory()->create(['member_id' => $ownerMember->id]);
    $activity = Activity::factory()->create(['owned_by' => $owner->id]);

    $data = ActivityData::fromModel(
        $activity->load(['owner.member', 'type'])
    );

    expect($data->owner_thumb_url)->toBeNull();
});

// ── Detail modal — member "Regarding" link ─────────────────────────────────────

it('renders a member regarding as a new-tab link with an avatar photo', function () {
    Storage::fake('public');

    $user = User::factory()->owner()->create();
    $member = Member::factory()->contact()->create([
        'name' => 'Ada Lovelace',
        'icon_thumb_url' => 'members/icons/ada-thumb.png',
    ]);
    $activity = Activity::factory()->forMember($member)->create();

    Volt::actingAs($user)
        ->test('calendar.activity-detail-modal')
        ->call('open', activityId: $activity->id)
        ->assertSee('Ada Lovelace')
        ->assertSeeHtml('href="'.route('members.show', $member->id).'"')
        ->assertSeeHtml('target="_blank"')
        ->assertSeeHtml('<img');
});

it('renders a member regarding link with initials when no photo is set', function () {
    $user = User::factory()->owner()->create();
    $member = Member::factory()->contact()->create([
        'name' => 'Grace Hopper',
        'icon_thumb_url' => null,
    ]);
    $activity = Activity::factory()->forMember($member)->create();

    Volt::actingAs($user)
        ->test('calendar.activity-detail-modal')
        ->call('open', activityId: $activity->id)
        ->assertSee('Grace Hopper')
        ->assertSeeHtml('href="'.route('members.show', $member->id).'"')
        ->assertSeeHtml('target="_blank"')
        ->assertSee('GH');
});

it('renders a non-member regarding as a badge without a member link', function () {
    $user = User::factory()->owner()->create();
    $product = Product::factory()->create(['name' => 'Par Can 64']);
    $activity = Activity::factory()->forProduct($product)->create();

    Volt::actingAs($user)
        ->test('calendar.activity-detail-modal')
        ->call('open', activityId: $activity->id)
        ->assertSee('Par Can 64')
        ->assertSee('Product')
        ->assertDontSeeHtml('href="'.route('members.show', $product->id).'"');
});

// ── Authorization — modal access gate (mount) ──────────────────────────────────

it('forbids mounting the form modal without activities.access', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)
        ->test('calendar.activity-form-modal')
        ->assertForbidden();
});

it('forbids mounting the detail modal without activities.access', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)
        ->test('calendar.activity-detail-modal')
        ->assertForbidden();
});

it('allows mounting the modals with activities.access', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('activities.access');

    Volt::actingAs($user)
        ->test('calendar.activity-form-modal')
        ->assertOk();

    Volt::actingAs($user)
        ->test('calendar.activity-detail-modal')
        ->assertOk();
});

// ── Authorization — modal actions ──────────────────────────────────────────────

it('forbids saving from the form modal without activities.create', function () {
    // Access (to pass mount) and view, but NOT create — the permission under test.
    $user = User::factory()->create();
    $user->givePermissionTo(['activities.access', 'activities.view']);

    Volt::actingAs($user)
        ->test('calendar.activity-form-modal')
        ->call('open')
        ->set('subject', 'Unauthorized activity')
        ->call('save')
        ->assertForbidden();

    expect(Activity::query()->where('subject', 'Unauthorized activity')->exists())->toBeFalse();
});

it('forbids completing from the detail modal without activities.complete', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['activities.access', 'activities.view']);
    $activity = Activity::factory()->create();

    Volt::actingAs($user)
        ->test('calendar.activity-detail-modal')
        ->call('open', activityId: $activity->id)
        ->call('complete')
        ->assertForbidden();

    expect($activity->fresh()->completed)->toBeFalse();
});

it('forbids deleting from the detail modal without activities.delete', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['activities.access', 'activities.view']);
    $activity = Activity::factory()->create();

    Volt::actingAs($user)
        ->test('calendar.activity-detail-modal')
        ->call('open', activityId: $activity->id)
        ->call('delete')
        ->assertForbidden();

    expect(Activity::query()->find($activity->id))->not->toBeNull();
});
