<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /** @use HasFactory<\Database\Factories\SettingFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
    ];

    /**
     * Scope to filter settings by group.
     *
     * @param  Builder<Setting>  $query
     * @return Builder<Setting>
     */
    public function scopeForGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    /**
     * Scope to filter settings by key within a group.
     *
     * @param  Builder<Setting>  $query
     * @return Builder<Setting>
     */
    public function scopeForKey(Builder $query, string $group, string $key): Builder
    {
        return $query->where('group', $group)->where('key', $key);
    }
}
