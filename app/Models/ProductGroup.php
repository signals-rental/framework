<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasCustomFields;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductGroup extends Model implements HasSchema
{
    /** @use HasFactory<\Database\Factories\ProductGroupFactory> */
    use HasAttachments, HasCustomFields, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'parent_id',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('name')->label('Name')->required()->searchable()->filterable()->sortable();
        $builder->text('description')->label('Description')->searchable();
        $builder->relation('parent_id')->label('Parent Group')
            ->relation('parent', 'belongsTo', self::class, 'name')
            ->filterable();
        $builder->integer('sort_order')->label('Sort Order')->sortable();
        $builder->datetime('created_at')->label('Created')->sortable()->filterable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<\App\Models\Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Scope to root groups (no parent).
     *
     * @param  Builder<ProductGroup>  $query
     * @return Builder<ProductGroup>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to order by sort_order.
     *
     * @param  Builder<ProductGroup>  $query
     * @return Builder<ProductGroup>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }
}
