<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string $entity_type
 * @property string $visibility
 * @property int|null $user_id
 * @property bool $is_default
 * @property array<int, string> $columns
 * @property array<int, array<string, mixed>> $filters
 * @property string|null $sort_column
 * @property string $sort_direction
 * @property int $per_page
 * @property array<string, mixed> $config
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class CustomView extends Model
{
    /** @use HasFactory<\Database\Factories\CustomViewFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'entity_type',
        'visibility',
        'user_id',
        'is_default',
        'columns',
        'filters',
        'sort_column',
        'sort_direction',
        'per_page',
        'config',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'columns' => 'array',
            'filters' => 'array',
            'config' => 'array',
            'is_default' => 'boolean',
            'per_page' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<\Spatie\Permission\Models\Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(\Spatie\Permission\Models\Role::class, 'custom_view_roles')
            ->withPivot('created_at');
    }

    /**
     * Scope to views for a given entity type.
     *
     * @param  Builder<CustomView>  $query
     * @return Builder<CustomView>
     */
    public function scopeForEntity(Builder $query, string $entityType): Builder
    {
        return $query->where('entity_type', $entityType);
    }

    /**
     * Scope to system default views.
     *
     * @param  Builder<CustomView>  $query
     * @return Builder<CustomView>
     */
    public function scopeSystemDefaults(Builder $query): Builder
    {
        return $query->where('visibility', 'system')->where('is_default', true);
    }

    /**
     * Scope to views visible to a given user.
     *
     * Includes system views, the user's personal views, and role-shared views
     * where the user has a matching role.
     *
     * @param  Builder<CustomView>  $query
     * @return Builder<CustomView>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('visibility', 'system')
                ->orWhere(function (Builder $q) use ($user) {
                    $q->where('visibility', 'personal')
                        ->where('user_id', $user->id);
                })
                ->orWhere(function (Builder $q) use ($user) {
                    $q->where('visibility', 'shared')
                        ->whereHas('roles', function (Builder $q) use ($user) {
                            $q->whereIn('roles.id', $user->roles->pluck('id'));
                        });
                });
        });
    }
}
