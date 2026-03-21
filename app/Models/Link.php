<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Link extends Model implements HasSchema
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

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('url')->label('URL')->required()->searchable()->filterable()->sortable();
        $builder->string('name')->label('Name')->searchable()->filterable()->sortable();
        $builder->relation('type_id')->label('Type')
            ->relation('type', 'belongsTo', ListValue::class, 'name')
            ->filterable();
        $builder->datetime('created_at')->label('Created')->sortable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
    }

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
