<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Enums\BasePeriod;
use App\Enums\CalculationStrategyType;
use App\Services\RateEngine\RateResolver;
use App\Services\SchemaBuilder;
use Database\Factories\RateDefinitionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CalculationStrategyType $calculation_strategy
 * @property BasePeriod|null $base_period
 * @property list<string> $enabled_modifiers
 * @property array<string, mixed> $strategy_config
 * @property array<string, array<string, mixed>> $modifier_configs
 */
class RateDefinition extends Model implements HasSchema
{
    /** @use HasFactory<RateDefinitionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // A cached resolution carries its loaded definition, so a definition edit
        // must bust all resolution caches. Skip when nothing relevant changed: a
        // brand-new definition has no product rates yet, and an unchanged save
        // (e.g. idempotent preset re-seeding) needs no invalidation.
        static::saved(static function (RateDefinition $definition): void {
            if ($definition->wasRecentlyCreated || ! $definition->wasChanged()) {
                return;
            }

            RateResolver::flushAll();
        });

        static::deleted(static function (): void {
            RateResolver::flushAll();
        });
    }

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'calculation_strategy',
        'base_period',
        'enabled_modifiers',
        'strategy_config',
        'modifier_configs',
        'is_preset',
        'preset_slug',
        'cloned_from_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'calculation_strategy' => CalculationStrategyType::class,
            'base_period' => BasePeriod::class,
            'enabled_modifiers' => 'array',
            'strategy_config' => 'array',
            'modifier_configs' => 'array',
            'is_preset' => 'boolean',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('name')->label('Name')->required()->searchable()->filterable()->sortable();
        $builder->text('description')->label('Description')->searchable();
        $builder->enum('calculation_strategy')->label('Calculation Strategy')->required()->filterable()->sortable()->groupable();
        $builder->enum('base_period')->label('Base Period')->filterable()->sortable()->groupable();
        $builder->json('enabled_modifiers')->label('Enabled Modifiers');
        $builder->boolean('is_preset')->label('Preset')->filterable()->sortable()->groupable();
        $builder->string('preset_slug')->label('Preset Slug')->filterable();
        $builder->relation('cloned_from_id')->label('Cloned From')
            ->relation('clonedFrom', 'belongsTo', RateDefinition::class, 'name')
            ->filterable();
        $builder->datetime('created_at')->label('Created')->sortable()->filterable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
    }

    /**
     * The definition this one was duplicated from, if any.
     *
     * @return BelongsTo<RateDefinition, $this>
     */
    public function clonedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cloned_from_id');
    }

    /**
     * Product rate assignments using this definition.
     *
     * @return HasMany<ProductRate, $this>
     */
    public function productRates(): HasMany
    {
        return $this->hasMany(ProductRate::class);
    }

    /**
     * Scope to preset (framework-shipped) definitions.
     *
     * @param  Builder<RateDefinition>  $query
     * @return Builder<RateDefinition>
     */
    public function scopePresets(Builder $query): Builder
    {
        return $query->where('is_preset', true);
    }

    /**
     * Scope to custom (user-created) definitions.
     *
     * @param  Builder<RateDefinition>  $query
     * @return Builder<RateDefinition>
     */
    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('is_preset', false);
    }
}
