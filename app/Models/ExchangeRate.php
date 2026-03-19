<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ExchangeRate extends Model
{
    /** @use HasFactory<\Database\Factories\ExchangeRateFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'source_currency_code',
        'target_currency_code',
        'rate',
        'inverse_rate',
        'source',
        'effective_at',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'string',
            'inverse_rate' => 'string',
            'effective_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Scope to rates effective at a given date.
     *
     * @param  Builder<ExchangeRate>  $query
     * @return Builder<ExchangeRate>
     */
    public function scopeEffectiveAt(Builder $query, Carbon $date): Builder
    {
        return $query->where('effective_at', '<=', $date)
            ->where(function (Builder $query) use ($date) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $date);
            });
    }

    /**
     * Scope to a specific currency pair.
     *
     * @param  Builder<ExchangeRate>  $query
     * @return Builder<ExchangeRate>
     */
    public function scopeForPair(Builder $query, string $from, string $to): Builder
    {
        return $query->where('source_currency_code', $from)
            ->where('target_currency_code', $to);
    }
}
