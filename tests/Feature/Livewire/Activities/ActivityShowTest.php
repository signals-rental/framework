<?php

use App\Models\ActionLog;
use App\Models\Activity;
use App\Models\CustomField;
use App\Models\Member;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

// ── Regarding — member link ────────────────────────────────────────────────────

it('renders a member regarding as a new-tab link with an avatar photo', function () {
    Storage::fake('public');

    $member = Member::factory()->contact()->create([
        'name' => 'Ada Lovelace',
        'icon_thumb_url' => 'members/icons/ada-thumb.png',
    ]);
    $activity = Activity::factory()->forMember($member)->create();

    Volt::test('activities.show', ['activity' => $activity])
        ->assertSee('Ada Lovelace')
        ->assertSeeHtml('href="'.route('members.show', $member->id).'"')
        ->assertSeeHtml('target="_blank"')
        ->assertSeeHtml('<img');
});

it('renders a member regarding link with initials when no photo is set', function () {
    $member = Member::factory()->contact()->create([
        'name' => 'Grace Hopper',
        'icon_thumb_url' => null,
    ]);
    $activity = Activity::factory()->forMember($member)->create();

    Volt::test('activities.show', ['activity' => $activity])
        ->assertSee('Grace Hopper')
        ->assertSeeHtml('href="'.route('members.show', $member->id).'"')
        ->assertSeeHtml('target="_blank"')
        ->assertSee('GH');
});

it('renders a non-member regarding as a badge without a member link', function () {
    $product = Product::factory()->create(['name' => 'Par Can 64']);
    $activity = Activity::factory()->forProduct($product)->create();

    Volt::test('activities.show', ['activity' => $activity])
        ->assertSee('Par Can 64')
        ->assertSee('Product')
        ->assertDontSeeHtml('href="'.route('members.show', $product->id).'"');
});

// ── Tabs ────────────────────────────────────────────────────────────────────

it('renders all four tabs on the show page', function () {
    $activity = Activity::factory()->create();

    Volt::test('activities.show', ['activity' => $activity])
        ->assertSee('Details')
        ->assertSee('Custom Fields')
        ->assertSee('Files')
        ->assertSee('Timeline');
});

it('displays the activity custom field values', function () {
    $field = CustomField::factory()->string()->forModule('Activity')->create([
        'display_name' => 'Call Outcome',
    ]);
    $activity = Activity::factory()->create();
    $activity->customFieldValues()->create([
        'custom_field_id' => $field->id,
        'value_string' => 'Voicemail left',
    ]);

    Volt::test('activities.show', ['activity' => $activity])
        ->assertSee('Call Outcome')
        ->assertSee('Voicemail left');
});

it('shows an empty custom fields message when none are configured', function () {
    $activity = Activity::factory()->create();

    Volt::test('activities.show', ['activity' => $activity])
        ->assertSee('No custom fields have been configured for activities.');
});

it('renders the files tab with the attachment count and upload control', function () {
    $activity = Activity::factory()->create();

    Volt::test('activities.show', ['activity' => $activity])
        ->assertSee('Upload File')
        ->assertSee('No Files');
});

// ── Live timeline (action_logs) ───────────────────────────────────────────────

it('renders a live timeline backed by action_logs for the activity', function () {
    $activity = Activity::factory()->create();

    ActionLog::query()->create([
        'user_id' => $this->user->id,
        'action' => 'activity.created',
        'auditable_type' => $activity->getMorphClass(),
        'auditable_id' => $activity->id,
    ]);

    Volt::test('activities.show', ['activity' => $activity])
        ->assertSee('Activity Timeline')
        ->assertSee('Activity Created');
});

it('shows an empty timeline message when there is no audit history', function () {
    $activity = Activity::factory()->create();

    Volt::test('activities.show', ['activity' => $activity])
        ->assertSee('No recorded history for this activity yet.');
});

// ── Actions ──────────────────────────────────────────────────────────────────

it('completes the activity via the Complete button', function () {
    $activity = Activity::factory()->create(['completed' => false]);

    Volt::test('activities.show', ['activity' => $activity])
        ->call('completeActivity');

    expect($activity->fresh()->completed)->toBeTrue();
});

it('deletes the activity and redirects to the index', function () {
    $activity = Activity::factory()->create();

    Volt::test('activities.show', ['activity' => $activity])
        ->call('deleteActivity')
        ->assertRedirect(route('activities.index'));

    expect(Activity::find($activity->id))->toBeNull();
});
