<?php

namespace App\Models;

use App\Enums\ShortagePolicy;
use Database\Factories\ShortageAcknowledgementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Confirmation-gate acknowledgement (shortage-resolution-sub-hires.md §7.3): the
 * record of a user proceeding past a shortage warning during quote → order
 * conversion, with a frozen snapshot of the shortage state at that moment.
 *
 * @property int $id
 * @property int $opportunity_id
 * @property int|null $user_id
 * @property Carbon $acknowledged_at
 * @property ShortagePolicy $policy_at_time
 * @property bool $permission_used
 * @property list<array<string, mixed>> $shortages_snapshot
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ShortageAcknowledgement extends Model
{
    /** @use HasFactory<ShortageAcknowledgementFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'opportunity_id',
        'user_id',
        'acknowledged_at',
        'policy_at_time',
        'permission_used',
        'shortages_snapshot',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
            'policy_at_time' => ShortagePolicy::class,
            'permission_used' => 'boolean',
            'shortages_snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Opportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'user_id');
    }
}
