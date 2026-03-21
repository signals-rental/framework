<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model implements HasSchema
{
    /** @use HasFactory<\Database\Factories\CurrencyFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'symbol_position',
        'thousand_separator',
        'decimal_separator',
        'is_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'decimal_places' => 'integer',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('code')->label('Code')->required()->filterable()->sortable();
        $builder->string('name')->label('Name')->required()->searchable()->filterable()->sortable();
        $builder->string('symbol')->label('Symbol')->filterable();
        $builder->integer('decimal_places')->label('Decimal Places');
        $builder->string('symbol_position')->label('Symbol Position')->filterable();
        $builder->boolean('is_enabled')->label('Enabled')->filterable()->sortable()->groupable();
        $builder->datetime('created_at')->label('Created')->sortable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
    }

    /**
     * Scope to enabled currencies.
     *
     * @param  Builder<Currency>  $query
     * @return Builder<Currency>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
