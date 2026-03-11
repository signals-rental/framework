<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListValue extends Model
{
    /** @use HasFactory<\Database\Factories\ListValueFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'list_name_id',
        'name',
        'parent_id',
        'sort_order',
        'is_system',
        'is_active',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ListName, $this>
     */
    public function listName(): BelongsTo
    {
        return $this->belongsTo(ListName::class);
    }

    /**
     * @return BelongsTo<ListValue, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ListValue::class, 'parent_id');
    }

    /**
     * @return HasMany<ListValue, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(ListValue::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Scope to active values.
     *
     * @param  Builder<ListValue>  $query
     * @return Builder<ListValue>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
