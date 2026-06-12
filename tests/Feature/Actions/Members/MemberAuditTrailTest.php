<?php

use App\Actions\Members\AnonymiseMember;
use App\Actions\Members\ArchiveMember;
use App\Actions\Members\CreateMember;
use App\Actions\Members\DeleteMember;
use App\Actions\Members\MergeMember;
use App\Actions\Members\RestoreMember;
use App\Actions\Members\UpdateMember;
use App\Data\Members\CreateMemberData;
use App\Data\Members\MergeMemberData;
use App\Data\Members\UpdateMemberData;
use App\Enums\MembershipType;
use App\Jobs\DeliverWebhook;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;

/**
 * End-to-end audit-trail coverage for member actions.
 *
 * Unlike the other action tests, these intentionally do NOT fake AuditableEvent,
 * so the auto-discovered LogAction listener runs and writes an action_logs row.
 * This proves the audit trail is persisted, not merely that the event fires.
 * DeliverWebhook is faked so no real webhook delivery is attempted.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    Queue::fake([DeliverWebhook::class]);
    $this->actingAs(User::factory()->owner()->create());
});

it('records an action_logs row when a member is created', function () {
    $result = (new CreateMember)(CreateMemberData::from([
        'name' => 'Audit Org',
        'membership_type' => MembershipType::Organisation->value,
    ]));

    $this->assertDatabaseHas('action_logs', [
        'action' => 'member.created',
        'auditable_type' => Member::class,
        'auditable_id' => $result->id,
    ]);
});

it('records an action_logs row when a member is updated', function () {
    $member = Member::factory()->organisation()->create();

    (new UpdateMember)($member, UpdateMemberData::from(['name' => 'Renamed Org']));

    $this->assertDatabaseHas('action_logs', [
        'action' => 'member.updated',
        'auditable_type' => Member::class,
        'auditable_id' => $member->id,
    ]);
});

it('records an action_logs row when a member is archived', function () {
    $member = Member::factory()->organisation()->create();

    (new ArchiveMember)($member);

    $this->assertDatabaseHas('action_logs', [
        'action' => 'member.archived',
        'auditable_type' => Member::class,
        'auditable_id' => $member->id,
    ]);
});

it('records an action_logs row when a member is restored', function () {
    $member = Member::factory()->organisation()->create();
    (new ArchiveMember)($member);

    (new RestoreMember)($member->fresh());

    $this->assertDatabaseHas('action_logs', [
        'action' => 'member.restored',
        'auditable_type' => Member::class,
        'auditable_id' => $member->id,
    ]);
});

it('records an action_logs row when a member is deleted', function () {
    $member = Member::factory()->organisation()->create();

    (new DeleteMember)($member);

    $this->assertDatabaseHas('action_logs', [
        'action' => 'member.deleted',
        'auditable_type' => Member::class,
        'auditable_id' => $member->id,
    ]);
});

it('records an action_logs row on the primary member when two members are merged', function () {
    $primary = Member::factory()->organisation()->create();
    $secondary = Member::factory()->organisation()->create();

    (new MergeMember)(MergeMemberData::from([
        'primary_id' => $primary->id,
        'secondary_id' => $secondary->id,
    ]));

    $this->assertDatabaseHas('action_logs', [
        'action' => 'member.merged',
        'auditable_type' => Member::class,
        'auditable_id' => $primary->id,
    ]);
});

it('records an action_logs row when a member is anonymised', function () {
    $member = Member::factory()->organisation()->create();

    (new AnonymiseMember)($member);

    $this->assertDatabaseHas('action_logs', [
        'action' => 'member.anonymised',
        'auditable_type' => Member::class,
        'auditable_id' => $member->id,
    ]);
});
