<?php

namespace App\Models;

use Database\Factories\OpportunityParticipantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A member associated with an opportunity in a named role — a plain,
 * NON-event-sourced CRM association (mirrors {@see ActivityParticipant}).
 *
 * Participants are deliberately decoupled from the Verbs event stream: the row is
 * written only by plain actions and never by a Verbs event/apply()/handle(), so a
 * Verbs replay of the opportunity stream rebuilds its projection without touching
 * participants. The unique (opportunity_id, member_id) constraint keeps a member
 * from being attached to the same opportunity twice; `role` is a free-text string
 * (the UI offers a suggested set) and `mute` opts the member out of opportunity
 * notifications.
 *
 * @property int $id
 * @property int $opportunity_id
 * @property int $member_id
 * @property string|null $role
 * @property bool $mute
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OpportunityParticipant extends Model
{
    /** @use HasFactory<OpportunityParticipantFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'opportunity_id',
        'member_id',
        'role',
        'mute',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mute' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Opportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
