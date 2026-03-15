<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFieldMultiValue extends Model
{
    /** @use HasFactory<\Database\Factories\CustomFieldMultiValueFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'custom_field_value_id',
        'list_value_id',
    ];

    /**
     * @return BelongsTo<CustomFieldValue, $this>
     */
    public function customFieldValue(): BelongsTo
    {
        return $this->belongsTo(CustomFieldValue::class);
    }

    /**
     * @return BelongsTo<ListValue, $this>
     */
    public function listValue(): BelongsTo
    {
        return $this->belongsTo(ListValue::class);
    }
}
