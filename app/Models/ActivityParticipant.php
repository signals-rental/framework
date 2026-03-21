<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityParticipant extends Model
{
    /** @use HasFactory<\Database\Factories\ActivityParticipantFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'activity_id',
        'member_id',
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
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
