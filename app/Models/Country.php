<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model implements HasSchema
{
    /** @use HasFactory<\Database\Factories\CountryFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'code3',
        'name',
        'currency_code',
        'phone_prefix',
        'default_timezone',
        'default_date_format',
        'default_time_format',
        'default_number_format',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('code')->label('Code')->required()->filterable()->sortable();
        $builder->string('code3')->label('ISO Alpha-3')->filterable();
        $builder->string('name')->label('Name')->required()->searchable()->filterable()->sortable();
        $builder->string('currency_code')->label('Currency Code')->filterable();
        $builder->string('phone_prefix')->label('Phone Prefix')->filterable();
        $builder->string('default_timezone')->label('Default Timezone');
        $builder->boolean('is_active')->label('Active')->filterable()->sortable()->groupable();
        $builder->datetime('created_at')->label('Created')->sortable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
    }

    /**
     * Scope to active countries.
     *
     * @param  Builder<Country>  $query
     * @return Builder<Country>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
