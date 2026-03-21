<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActionLog extends Model implements HasSchema
{
    /** @use HasFactory<\Database\Factories\ActionLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('action')->label('Action')->required()->filterable()->sortable()->groupable();
        $builder->string('auditable_type')->label('Entity Type')->filterable()->groupable();
        $builder->integer('auditable_id')->label('Entity ID')->filterable();
        $builder->relation('user_id')->label('User')
            ->relation('user', 'belongsTo', User::class, 'name')
            ->filterable();
        $builder->string('ip_address')->label('IP Address')->filterable();
        $builder->datetime('created_at')->label('Created')->sortable()->filterable();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<ActionLog>  $query
     * @return Builder<ActionLog>
     */
    public function scopeForEntity(Builder $query, string $type, ?int $id = null): Builder
    {
        $query->where('auditable_type', $type);

        if ($id !== null) {
            $query->where('auditable_id', $id);
        }

        return $query;
    }

    /**
     * @param  Builder<ActionLog>  $query
     * @return Builder<ActionLog>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<ActionLog>  $query
     * @return Builder<ActionLog>
     */
    public function scopeForAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * @param  Builder<ActionLog>  $query
     * @return Builder<ActionLog>
     */
    public function scopeCreatedBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}
