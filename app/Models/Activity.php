<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Enums\ActivityPriority;
use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\TimeStatus;
use App\Models\Traits\HasCustomFields;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model implements HasSchema
{
    /** @use HasFactory<\Database\Factories\ActivityFactory> */
    use HasCustomFields, HasFactory;

    /** @var array<string, class-string<Model>> */
    public static array $regardingMap = [
        'Member' => Member::class,
        'Product' => Product::class,
        'StockLevel' => StockLevel::class,
    ];

    /**
     * Resolve a CRMS short regarding_type to a fully-qualified class name.
     */
    public static function resolveRegardingType(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        if (! isset(self::$regardingMap[$type])) {
            throw new \InvalidArgumentException(
                "Unknown regarding_type '{$type}'. Valid types: ".implode(', ', array_keys(self::$regardingMap))
            );
        }

        return self::$regardingMap[$type];
    }

    /**
     * Convert a fully-qualified class name to a CRMS short regarding_type.
     */
    public static function shortRegardingType(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        $flipped = array_flip(self::$regardingMap);

        return $flipped[$type] ?? class_basename($type);
    }

    /** @var list<string> */
    protected $fillable = [
        'subject',
        'description',
        'location',
        'regarding_id',
        'regarding_type',
        'owned_by',
        'starts_at',
        'ends_at',
        'priority',
        'type_id',
        'status_id',
        'completed',
        'time_status',
        'tag_list',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type_id' => ActivityType::class,
            'status_id' => ActivityStatus::class,
            'priority' => ActivityPriority::class,
            'time_status' => TimeStatus::class,
            'completed' => 'boolean',
            'tag_list' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('subject')->label('Subject')->required()->searchable()->filterable()->sortable();
        $builder->text('description')->label('Description')->searchable();
        $builder->string('location')->label('Location')->searchable()->filterable();
        $builder->enum('type_id')->label('Type')->filterable()->sortable()->groupable();
        $builder->enum('status_id')->label('Status')->filterable()->sortable()->groupable();
        $builder->enum('priority')->label('Priority')->filterable()->sortable()->groupable();
        $builder->enum('time_status')->label('Time Status')->filterable();
        $builder->boolean('completed')->label('Completed')->filterable()->sortable();
        $builder->string('regarding_type')->label('Regarding Type')->filterable();
        $builder->integer('regarding_id')->label('Regarding ID')->filterable();
        $builder->relation('owned_by')->label('Owner')
            ->relation('owner', 'belongsTo', User::class, 'name')
            ->filterable();
        $builder->datetime('starts_at')->label('Starts At')->filterable()->sortable();
        $builder->datetime('ends_at')->label('Ends At')->filterable()->sortable();
        $builder->json('tag_list')->label('Tags')->searchable();
        $builder->datetime('created_at')->label('Created')->sortable()->filterable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owned_by');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function regarding(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<ActivityParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ActivityParticipant::class);
    }

    /**
     * @return BelongsToMany<Member, $this>
     */
    public function participantMembers(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'activity_participants')
            ->withPivot('mute')
            ->withTimestamps();
    }

    /**
     * Scope to activities for a specific member (as regarding).
     *
     * @param  Builder<Activity>  $query
     * @return Builder<Activity>
     */
    public function scopeForMember(Builder $query, int $memberId): Builder
    {
        return $query->where('regarding_type', Member::class)->where('regarding_id', $memberId);
    }

    /**
     * Scope to activities for a specific product (as regarding).
     *
     * @param  Builder<Activity>  $query
     * @return Builder<Activity>
     */
    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('regarding_type', Product::class)->where('regarding_id', $productId);
    }

    /**
     * Scope to activities for a specific stock level (as regarding).
     *
     * @param  Builder<Activity>  $query
     * @return Builder<Activity>
     */
    public function scopeForStockLevel(Builder $query, int $stockLevelId): Builder
    {
        return $query->where('regarding_type', StockLevel::class)->where('regarding_id', $stockLevelId);
    }

    /**
     * Scope to pending (not completed) activities.
     *
     * @param  Builder<Activity>  $query
     * @return Builder<Activity>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('completed', false);
    }

    /**
     * Scope to completed activities.
     *
     * @param  Builder<Activity>  $query
     * @return Builder<Activity>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('completed', true);
    }

    /**
     * Scope to overdue activities (starts_at in the past and not completed).
     *
     * @param  Builder<Activity>  $query
     * @return Builder<Activity>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('completed', false)
            ->whereNotNull('starts_at')
            ->where('starts_at', '<', now());
    }

    /**
     * Scope to activities owned by a specific user.
     *
     * @param  Builder<Activity>  $query
     * @return Builder<Activity>
     */
    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('owned_by', $userId);
    }
}
