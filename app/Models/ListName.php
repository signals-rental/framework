<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListName extends Model
{
    /** @use HasFactory<\Database\Factories\ListNameFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'is_system',
        'is_hierarchical',
        'plugin_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_hierarchical' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ListValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(ListValue::class)->orderBy('sort_order');
    }
}
