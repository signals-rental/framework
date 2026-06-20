<?php

use App\Actions\Opportunities\AddOpportunityParticipant;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\RemoveOpportunityParticipant;
use App\Actions\Opportunities\UpdateOpportunityParticipant;
use App\Data\Opportunities\AddOpportunityParticipantData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\UpdateOpportunityParticipantData;
use App\Events\AuditableEvent;
use App\Models\ActionLog;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityParticipant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| C3f — opportunity participants (plain, NON-event-sourced CRM associations)
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
});

function participantOpportunity(): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'With participants']));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

it('adds a participant to an opportunity in a role', function () {
    $opportunity = participantOpportunity();
    $member = Member::factory()->contact()->create();

    $data = (new AddOpportunityParticipant)($opportunity, AddOpportunityParticipantData::from([
        'member_id' => $member->id,
        'role' => 'Primary contact',
        'mute' => false,
    ]));

    expect($data->member_id)->toBe($member->id)
        ->and($data->role)->toBe('Primary contact')
        ->and($data->mute)->toBeFalse()
        ->and($data->opportunity_id)->toBe($opportunity->id);

    $this->assertDatabaseHas('opportunity_participants', [
        'opportunity_id' => $opportunity->id,
        'member_id' => $member->id,
        'role' => 'Primary contact',
        'mute' => false,
    ]);
});

it('rejects a duplicate member on the same opportunity', function () {
    $opportunity = participantOpportunity();
    $member = Member::factory()->contact()->create();

    (new AddOpportunityParticipant)($opportunity, AddOpportunityParticipantData::from([
        'member_id' => $member->id,
    ]));

    (new AddOpportunityParticipant)($opportunity, AddOpportunityParticipantData::from([
        'member_id' => $member->id,
    ]));
})->throws(ValidationException::class);

it('allows the same member on two different opportunities', function () {
    $opportunityA = participantOpportunity();
    $opportunityB = participantOpportunity();
    $member = Member::factory()->contact()->create();

    (new AddOpportunityParticipant)($opportunityA, AddOpportunityParticipantData::from(['member_id' => $member->id]));
    (new AddOpportunityParticipant)($opportunityB, AddOpportunityParticipantData::from(['member_id' => $member->id]));

    expect(OpportunityParticipant::query()->where('member_id', $member->id)->count())->toBe(2);
});

it('updates only the supplied participant fields', function () {
    $opportunity = participantOpportunity();
    $participant = OpportunityParticipant::factory()->for($opportunity)->create([
        'role' => 'Secondary contact',
        'mute' => false,
    ]);

    $result = (new UpdateOpportunityParticipant)(
        $participant,
        UpdateOpportunityParticipantData::from(['mute' => true]),
    );

    // role untouched (omitted), mute flipped.
    expect($result->role)->toBe('Secondary contact')
        ->and($result->mute)->toBeTrue();

    $this->assertDatabaseHas('opportunity_participants', [
        'id' => $participant->id,
        'role' => 'Secondary contact',
        'mute' => true,
    ]);
});

it('can clear the role to null', function () {
    $opportunity = participantOpportunity();
    $participant = OpportunityParticipant::factory()->for($opportunity)->create(['role' => 'Site contact']);

    $result = (new UpdateOpportunityParticipant)(
        $participant,
        UpdateOpportunityParticipantData::from(['role' => null]),
    );

    expect($result->role)->toBeNull();
});

it('removes a participant', function () {
    $opportunity = participantOpportunity();
    $participant = OpportunityParticipant::factory()->for($opportunity)->create();

    (new RemoveOpportunityParticipant)($participant);

    $this->assertDatabaseMissing('opportunity_participants', ['id' => $participant->id]);
});

it('exposes the participants relation on the opportunity', function () {
    $opportunity = participantOpportunity();
    OpportunityParticipant::factory()->count(2)->for($opportunity)->create();

    expect($opportunity->participants()->count())->toBe(2);
});

it('cascade-deletes participants when the opportunity is force-deleted', function () {
    $opportunity = participantOpportunity();
    $participant = OpportunityParticipant::factory()->for($opportunity)->create();

    $opportunity->forceDelete();

    $this->assertDatabaseMissing('opportunity_participants', ['id' => $participant->id]);
});

it('fires audit events for each participant mutation', function () {
    Event::fake([AuditableEvent::class]);

    $opportunity = participantOpportunity();
    $member = Member::factory()->contact()->create();

    $added = (new AddOpportunityParticipant)($opportunity, AddOpportunityParticipantData::from([
        'member_id' => $member->id,
        'role' => 'Account manager',
    ]));

    Event::assertDispatched(
        AuditableEvent::class,
        fn (AuditableEvent $event): bool => $event->action === 'opportunity.participant_added'
            && $event->newValues['member_id'] === $member->id,
    );

    $participant = OpportunityParticipant::query()->whereKey($added->id)->firstOrFail();

    (new UpdateOpportunityParticipant)($participant, UpdateOpportunityParticipantData::from(['mute' => true]));
    (new RemoveOpportunityParticipant)($participant);

    Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $e): bool => $e->action === 'opportunity.participant_updated');
    Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $e): bool => $e->action === 'opportunity.participant_removed');
});

it('records the add audit action in the action log', function () {
    $opportunity = participantOpportunity();
    $member = Member::factory()->contact()->create();

    (new AddOpportunityParticipant)($opportunity, AddOpportunityParticipantData::from(['member_id' => $member->id]));

    expect(ActionLog::query()->where('action', 'opportunity.participant_added')->count())->toBe(1);
});

it('denies adding a participant without permission', function () {
    $opportunity = participantOpportunity();
    $member = Member::factory()->contact()->create();

    $this->actingAs(User::factory()->create());

    (new AddOpportunityParticipant)($opportunity, AddOpportunityParticipantData::from(['member_id' => $member->id]));
})->throws(AuthorizationException::class);

it('denies updating a participant without permission', function () {
    $opportunity = participantOpportunity();
    $participant = OpportunityParticipant::factory()->for($opportunity)->create();

    $this->actingAs(User::factory()->create());

    (new UpdateOpportunityParticipant)($participant, UpdateOpportunityParticipantData::from(['mute' => true]));
})->throws(AuthorizationException::class);

it('denies removing a participant without permission', function () {
    $opportunity = participantOpportunity();
    $participant = OpportunityParticipant::factory()->for($opportunity)->create();

    $this->actingAs(User::factory()->create());

    (new RemoveOpportunityParticipant)($participant);
})->throws(AuthorizationException::class);
