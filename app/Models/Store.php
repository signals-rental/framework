<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Models\Traits\HasCustomFields;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Store extends Model implements HasSchema
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
