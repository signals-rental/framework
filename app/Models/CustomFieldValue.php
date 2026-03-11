<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CustomFieldValue extends Model
{
    /** @use HasFactory<\Database\Factories\CustomFieldValueFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'custom_field_id',
        'entity_type',
        'entity_id',
        'value_string',
        'value_text',
        'value_integer',
        'value_decimal',
        'value_boolean',
        'value_date',
        'value_datetime',
        'value_time',
        'value_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_boolean' => 'boolean',
            'value_decimal' => 'decimal:4',
            'value_date' => 'date',
            'value_datetime' => 'datetime',
            'value_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CustomField, $this>
     */
    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}
