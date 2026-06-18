<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Enums\ShortagePolicy;
use App\Models\Traits\HasCustomFields;
use App\Services\SchemaBuilder;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ShortagePolicy $shortage_policy
 */
class Store extends Model implements HasSchema
{
    /** @use HasFactory<StoreFactory> */
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
        'timezone',
        'is_default',
        'shortage_policy',
        'tag_list',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'shortage_policy' => ShortagePolicy::class,
            'tag_list' => 'array',
        ];
    }

    /**
     * The store's shortage confirmation-gate policy, falling back to the
     * framework default when the column is unset (legacy rows).
     */
    public function shortagePolicy(): ShortagePolicy
    {
        return $this->shortage_policy ?? ShortagePolicy::default();
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('name')->label('Name')->required()->searchable()->filterable()->sortable();
        $builder->string('street')->label('Street');
        $builder->string('city')->label('City')->filterable()->sortable();
        $builder->string('county')->label('County')->filterable();
        $builder->string('postcode')->label('Postcode')->filterable();
        $builder->string('country_code')->label('Country Code')->filterable();
        $builder->relation('country_id')->label('Country')
            ->relation('country', 'belongsTo', Country::class, 'name')
            ->filterable();
        $builder->string('phone')->label('Phone');
        $builder->string('email')->label('Email');
        $builder->string('timezone')->label('Timezone')->filterable();
        $builder->boolean('is_default')->label('Default Store')->filterable()->sortable();
        $builder->datetime('created_at')->label('Created')->sortable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
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
