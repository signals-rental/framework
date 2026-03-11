<?php

namespace App\Models;

use App\Models\Traits\HasCustomFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Store extends Model
{
    /** @use HasFactory<\Database\Factories\StoreFactory> */
    use HasCustomFields, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'street',
        'city',
        'county',
        'postcode',
        'country_code',
        'country_id',
        'phone',
        'email',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Scope to the default store.
     *
     * @param  Builder<Store>  $query
     * @return Builder<Store>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }
}
