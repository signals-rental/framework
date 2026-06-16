<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\MagicLinkTokenFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $consumed_at
 */
class MagicLinkToken extends Model
{
    /** @use HasFactory<MagicLinkTokenFactory> */
    use HasFactory;

    use Prunable;

    protected $table = 'magic_link_tokens';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'token_hash',
        'expires_at',
        'consumed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
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
     * The query identifying spent magic-link tokens that are safe to prune.
     *
     * A token is no longer useful once it has been consumed, or once it has been
     * expired long enough that no replay or audit reference remains relevant.
     * Consumed tokens are dropped on the next prune run; expired-but-unconsumed
     * tokens are kept for a 30-day grace window before removal. Run by the
     * scheduled `model:prune` command (see routes/console.php).
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->whereNotNull('consumed_at')
            ->orWhere('expires_at', '<', now()->subDays(30));
    }

    /**
     * Whether the token's expiry is in the past.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Whether the token has already been consumed (single-use).
     */
    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    /**
     * Whether the token can still be used to log in.
     */
    public function isUsable(): bool
    {
        return ! $this->isExpired() && ! $this->isConsumed();
    }

    protected static function newFactory(): MagicLinkTokenFactory
    {
        return MagicLinkTokenFactory::new();
    }
}
