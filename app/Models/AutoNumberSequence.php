<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoNumberSequence extends Model
{
    /** @use HasFactory<\Database\Factories\AutoNumberSequenceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'custom_field_id',
        'prefix',
        'suffix',
        'next_value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'next_value' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CustomField, $this>
     */
    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }
}
