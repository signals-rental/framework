<?php

namespace App\Models;

use App\Enums\CustomFieldType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomField extends Model
{
    /** @use HasFactory<\Database\Factories\CustomFieldFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'module_type',
        'field_type',
        'custom_field_group_id',
        'list_name_id',
        'sort_order',
        'is_required',
        'is_searchable',
        'settings',
        'validation_rules',
        'visibility_rules',
        'default_value',
        'plugin_name',
        'document_layout_name',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'field_type' => CustomFieldType::class,
            'is_required' => 'boolean',
            'is_searchable' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
            'validation_rules' => 'array',
            'visibility_rules' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CustomFieldGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomFieldGroup::class, 'custom_field_group_id');
    }

    /**
     * @return BelongsTo<ListName, $this>
     */
    public function listName(): BelongsTo
    {
        return $this->belongsTo(ListName::class);
    }

    /**
     * @return HasMany<CustomFieldValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    /**
     * Scope to fields for a given module type.
     *
     * @param  Builder<CustomField>  $query
     * @return Builder<CustomField>
     */
    public function scopeForModule(Builder $query, string $moduleType): Builder
    {
        return $query->where('module_type', $moduleType);
    }

    /**
     * Scope to active fields.
     *
     * @param  Builder<CustomField>  $query
     * @return Builder<CustomField>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
