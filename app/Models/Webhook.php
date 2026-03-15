<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    /** @use HasFactory<\Database\Factories\WebhookFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'url',
        'secret',
        'events',
        'is_active',
        'consecutive_failures',
        'disabled_at',
    ];

    /** @var list<string> */
    protected $hidden = [
        'secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'consecutive_failures' => 'integer',
            'disabled_at' => 'datetime',
            'secret' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<WebhookLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(WebhookLog::class);
    }

    /**
     * Check if this webhook is subscribed to a given event.
     */
    public function subscribedTo(string $event): bool
    {
        /** @var list<string> $events */
        $events = $this->events ?? [];

        return in_array('*', $events, true) || in_array($event, $events, true);
    }
}
