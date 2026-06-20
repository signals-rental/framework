<?php

namespace App\Actions\Opportunities;

use App\Data\Opportunities\ParticipantData;
use App\Data\Opportunities\UpdateOpportunityParticipantData;
use App\Events\AuditableEvent;
use App\Models\OpportunityParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Optional;

/**
 * Updates a participant's role and/or mute flag.
 *
 * Only the fields present on the DTO are merged over the participant's current
 * values; omitted fields are left untouched. Participants are plain,
 * non-event-sourced rows, so this is a normal Eloquent update; an AuditableEvent
 * is fired for audit parity (audit-only, like CreateOpportunitySection — not a
 * registered webhook event). The member association is immutable here.
 */
class UpdateOpportunityParticipant
{
    public function __invoke(OpportunityParticipant $participant, UpdateOpportunityParticipantData $data): ParticipantData
    {
        Gate::authorize('opportunities.edit');

        return DB::transaction(function () use ($participant, $data): ParticipantData {
            $old = [
                'role' => $participant->role,
                'mute' => $participant->mute,
            ];

            if (! $data->role instanceof Optional) {
                $participant->role = $data->role;
            }

            if (! $data->mute instanceof Optional) {
                $participant->mute = $data->mute;
            }

            $participant->save();

            event(new AuditableEvent(
                $participant,
                'opportunity.participant_updated',
                oldValues: $old,
                newValues: [
                    'role' => $participant->role,
                    'mute' => $participant->mute,
                ],
            ));

            return ParticipantData::fromModel($participant->load('member'));
        });
    }
}
