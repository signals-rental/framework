<?php

use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\CreateOpportunityData;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityParticipant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

function participantReadToken(User $user): string
{
    return $user->createToken('test', ['opportunities:read'])->plainTextToken;
}

function participantWriteToken(User $user): string
{
    return $user->createToken('test', ['opportunities:write'])->plainTextToken;
}

function makeParticipantApiOpportunity(User $actor): Opportunity
{
    Auth::login($actor);

    try {
        $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'API Participants']));

        return Opportunity::query()->whereKey($created->id)->firstOrFail();
    } finally {
        Auth::logout();
    }
}

describe('GET /api/v1/opportunities/{id}?include=participants', function () {
    it('returns participants[] with the nested member reference', function () {
        $opportunity = makeParticipantApiOpportunity($this->owner);
        $member = Member::factory()->contact()->create(['name' => 'Jane Roe']);
        OpportunityParticipant::factory()->for($opportunity)->create([
            'member_id' => $member->id,
            'role' => 'Primary contact',
            'mute' => true,
        ]);
        $token = participantReadToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$opportunity->id}?include=participants,participants.member")
            ->assertOk()
            ->assertJsonPath('opportunity.participants.0.role', 'Primary contact')
            ->assertJsonPath('opportunity.participants.0.mute', true)
            ->assertJsonPath('opportunity.participants.0.member_id', $member->id);

        expect($response->json('opportunity.participants.0.member.id'))->toBe($member->id)
            ->and($response->json('opportunity.participants.0.member.name'))->toBe('Jane Roe');
    });
});

describe('POST /api/v1/opportunities/{id}/participants', function () {
    it('attaches a member in a role', function () {
        $opportunity = makeParticipantApiOpportunity($this->owner);
        $member = Member::factory()->contact()->create();
        $token = participantWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/participants", [
                'member_id' => $member->id,
                'role' => 'Site contact',
                'mute' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('participant.member_id', $member->id)
            ->assertJsonPath('participant.role', 'Site contact');

        $this->assertDatabaseHas('opportunity_participants', [
            'opportunity_id' => $opportunity->id,
            'member_id' => $member->id,
            'role' => 'Site contact',
        ]);
    });

    it('validates a missing member_id', function () {
        $opportunity = makeParticipantApiOpportunity($this->owner);
        $token = participantWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/participants", ['role' => 'X'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['member_id']);
    });

    it('rejects a non-existent member', function () {
        $opportunity = makeParticipantApiOpportunity($this->owner);
        $token = participantWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/participants", ['member_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['member_id']);
    });

    it('rejects a duplicate member with a 422', function () {
        $opportunity = makeParticipantApiOpportunity($this->owner);
        $member = Member::factory()->contact()->create();
        OpportunityParticipant::factory()->for($opportunity)->create(['member_id' => $member->id]);
        $token = participantWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/participants", ['member_id' => $member->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['member_id']);
    });

    it('requires the opportunities:write ability', function () {
        $opportunity = makeParticipantApiOpportunity($this->owner);
        $member = Member::factory()->contact()->create();
        $token = participantReadToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/participants", ['member_id' => $member->id])
            ->assertForbidden();
    });
});

describe('PATCH /api/v1/opportunities/{id}/participants/{participant}', function () {
    it('updates the role and mute flag', function () {
        $opportunity = makeParticipantApiOpportunity($this->owner);
        $participant = OpportunityParticipant::factory()->for($opportunity)->create([
            'role' => 'Secondary contact', 'mute' => false,
        ]);
        $token = participantWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/opportunities/{$opportunity->id}/participants/{$participant->id}", [
                'role' => 'Account manager', 'mute' => true,
            ])
            ->assertOk()
            ->assertJsonPath('participant.role', 'Account manager')
            ->assertJsonPath('participant.mute', true);
    });

    it('404s when the participant belongs to another opportunity', function () {
        $opportunityA = makeParticipantApiOpportunity($this->owner);
        $opportunityB = makeParticipantApiOpportunity($this->owner);
        $participant = OpportunityParticipant::factory()->for($opportunityA)->create();
        $token = participantWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/opportunities/{$opportunityB->id}/participants/{$participant->id}", ['mute' => true])
            ->assertNotFound();
    });
});

describe('DELETE /api/v1/opportunities/{id}/participants/{participant}', function () {
    it('detaches the participant', function () {
        $opportunity = makeParticipantApiOpportunity($this->owner);
        $participant = OpportunityParticipant::factory()->for($opportunity)->create();
        $token = participantWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/opportunities/{$opportunity->id}/participants/{$participant->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('opportunity_participants', ['id' => $participant->id]);
    });

    it('requires the opportunities:write ability', function () {
        $opportunity = makeParticipantApiOpportunity($this->owner);
        $participant = OpportunityParticipant::factory()->for($opportunity)->create();
        $token = participantReadToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/opportunities/{$opportunity->id}/participants/{$participant->id}")
            ->assertForbidden();
    });
});
