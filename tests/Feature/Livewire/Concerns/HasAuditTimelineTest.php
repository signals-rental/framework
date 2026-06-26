<?php

use App\Models\ActionLog;
use App\Models\Activity;
use App\Models\Member;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('shapes member timeline entries with headline meta and actor body', function () {
    $member = Member::factory()->create(['name' => 'Audit Member']);

    ActionLog::query()->create([
        'user_id' => $this->user->id,
        'action' => 'member.updated',
        'auditable_type' => $member->getMorphClass(),
        'auditable_id' => $member->id,
    ]);

    Volt::test('members.show', ['member' => $member])
        ->assertSee('Member Updated')
        ->assertSee("by {$this->user->name}")
        ->assertSeeHtml('s-timeline-dot-blue');
});

it('maps member timeline colours for created, archived, and unmatched actions', function () {
    $member = Member::factory()->create();

    ActionLog::query()->create([
        'user_id' => $this->user->id,
        'action' => 'member.created',
        'auditable_type' => $member->getMorphClass(),
        'auditable_id' => $member->id,
    ]);
    ActionLog::query()->create([
        'user_id' => $this->user->id,
        'action' => 'member.archived',
        'auditable_type' => $member->getMorphClass(),
        'auditable_id' => $member->id,
    ]);
    ActionLog::query()->create([
        'user_id' => $this->user->id,
        'action' => 'member.exported',
        'auditable_type' => $member->getMorphClass(),
        'auditable_id' => $member->id,
    ]);

    Volt::test('members.show', ['member' => $member])
        ->assertSeeHtml('s-timeline-dot-green')
        ->assertSeeHtml('s-timeline-dot-red')
        ->assertSee('Member Exported');
});

it('omits the actor body when the audit log has no user', function () {
    $member = Member::factory()->create();

    ActionLog::query()->create([
        'user_id' => null,
        'action' => 'member.restored',
        'auditable_type' => $member->getMorphClass(),
        'auditable_id' => $member->id,
    ]);

    Volt::test('members.show', ['member' => $member])
        ->assertSee('Member Restored')
        ->assertDontSee('by ');
});

it('uses the activity-specific timeline colour override for completed actions', function () {
    $activity = Activity::factory()->create();

    ActionLog::query()->create([
        'user_id' => $this->user->id,
        'action' => 'activity.completed',
        'auditable_type' => $activity->getMorphClass(),
        'auditable_id' => $activity->id,
    ]);
    ActionLog::query()->create([
        'user_id' => $this->user->id,
        'action' => 'activity.cancelled',
        'auditable_type' => $activity->getMorphClass(),
        'auditable_id' => $activity->id,
    ]);

    Volt::test('activities.show', ['activity' => $activity])
        ->assertSeeHtml('s-timeline-dot-green')
        ->assertSeeHtml('s-timeline-dot-red')
        ->assertSee('Activity Completed')
        ->assertSee('Activity Cancelled');
});
