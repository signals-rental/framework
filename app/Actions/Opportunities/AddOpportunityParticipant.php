<?php

namespace App\Actions\Opportunities;

use App\Data\Opportunities\AddOpportunityParticipantData;
use App\Data\Opportunities\ParticipantData;
use App\Events\AuditableEvent;
use App\Models\Opportunity;
use App\Models\OpportunityParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Attaches a member to an opportunity in a named role.
 *
 * Participants are PLAIN, non-event-sourced CRM associations (mirrors
 * {@see CreateOpportunitySection} and the activity_participants precedent) — they
 * are created via a normal Eloquent insert, never a Verbs event, so a Verbs
 * replay of the opportunity stream never touches them. An AuditableEvent is fired
 * for audit-trail parity with the event-sourced mutations; like sections, this
 * audit action is NOT a registered webhook event, so the webhook bridge ignores
 * it (the model is not in DispatchWebhookForAuditableEvent::OWNED_MODELS).
 *
 * A member may be attached to a given opportunity only once — the duplicate is
 * rejected with a friendly validation error rather than a raw unique-constraint
 * violation.
 */
class AddOpportunityParticipant
{
    public function __invoke(Opportunity $opportunity, AddOpportunityParticipantData $data): ParticipantData
    {
        Gate::authorize('opportunities.edit');

        return DB::transaction(function () use ($opportunity, $data): ParticipantData {
            $exists = $opportunity->participants()
                ->where('member_id', $data->member_id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'member_id' => 'This member is already a participant on the opportunity.',
                ]);
            }

            $participant = OpportunityParticipant::create([
                'opportunity_id' => $opportunity->id,
                'member_id' => $data->member_id,
                'role' => $data->role,
                'mute' => $data->mute,
            ]);

            event(new AuditableEvent(
                $participant,
                'opportunity.participant_added',
                newValues: [
                    'member_id' => $participant->member_id,
                    'role' => $participant->role,
                    'mute' => $participant->mute,
                ],
            ));

            return ParticipantData::fromModel($participant->load('member'));
        });
    }
}
