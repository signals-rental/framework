<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Link extends Model
{
    /** @use HasFactory<\Database\Factories\LinkFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'linkable_type',
        'linkable_id',
        'url',
        'name',
        'type_id',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<ListValue, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(ListValue::class, 'type_id');
    }
}
