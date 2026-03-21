<?php

namespace App\Data\Activities;

use App\Enums\ActivityPriority;
use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\TimeStatus;
use App\Models\Activity;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class ActivityData extends Data
{
    /**
     * @param  array<string, mixed>  $custom_fields
     * @param  list<array<string, mixed>>  $participants
     * @param  array<string, mixed>|null  $regarding
     * @param  array<string, mixed>|null  $owner
     */
    public function __construct(
        public int $id,
        public string $subject,
        public ?string $description,
        public ?string $location,
        public ?int $regarding_id,
        public ?string $regarding_type,
        public int $owned_by,
        public ?string $starts_at,
        public ?string $ends_at,
        public int $priority,
        public int $type_id,
        public int $status_id,
        public bool $completed,
        public int $time_status,
        public array $custom_fields,
        public array $participants,
        public string $activity_type_name,
        public string $activity_status_name,
        public string $time_status_name,
        public string $created_at,
        public string $updated_at,
        public ?array $regarding = null,
        public ?array $owner = null,
    ) {}

    private static function formatTimestamp(\DateTimeInterface $timestamp): string
    {
        return Carbon::instance($timestamp)->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    public static function fromModel(Activity $activity): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $activity->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $activity->updated_at;

        /** @var ActivityType $type */
        $type = $activity->type_id;

        /** @var ActivityStatus $status */
        $status = $activity->status_id;

        /** @var ActivityPriority $priority */
        $priority = $activity->priority;

        /** @var TimeStatus $timeStatus */
        $timeStatus = $activity->time_status;

        /** @var Carbon|null $startsAt */
        $startsAt = $activity->starts_at;

        /** @var Carbon|null $endsAt */
        $endsAt = $activity->ends_at;

        $participants = [];
        if ($activity->relationLoaded('participants')) {
            $participants = $activity->participants->map(function ($participant) {
                $member = $participant->relationLoaded('member') ? $participant->member : null;

                return [
                    'id' => $participant->id,
                    'member_id' => $participant->member_id,
                    'member_name' => $member->name ?? '',
                    'mute' => $participant->mute,
                ];
            })->all();
        }

        $regarding = null;
        if ($activity->relationLoaded('regarding') && $activity->regarding) {
            /** @var \Illuminate\Database\Eloquent\Model $regardingModel */
            $regardingModel = $activity->regarding;
            $regarding = [
                'id' => $regardingModel->getKey(),
                'name' => $regardingModel->getAttribute('name') ?? '',
            ];
        }

        $owner = null;
        if ($activity->relationLoaded('owner') && $activity->owner) {
            $owner = [
                'id' => $activity->owner->id,
                'name' => $activity->owner->name,
            ];
        }

        return new self(
            id: $activity->id,
            subject: $activity->subject,
            description: $activity->description,
            location: $activity->location,
            regarding_id: $activity->regarding_id,
            regarding_type: Activity::shortRegardingType($activity->regarding_type),
            owned_by: $activity->owned_by,
            starts_at: $startsAt !== null ? self::formatTimestamp($startsAt) : null,
            ends_at: $endsAt !== null ? self::formatTimestamp($endsAt) : null,
            priority: $priority->value,
            type_id: $type->value,
            status_id: $status->value,
            completed: $activity->completed,
            time_status: $timeStatus->value,
            custom_fields: $activity->relationLoaded('customFieldValues') ? $activity->custom_fields : [],
            participants: $participants,
            activity_type_name: $type->label(),
            activity_status_name: $status->label(),
            time_status_name: $timeStatus->label(),
            created_at: self::formatTimestamp($createdAt),
            updated_at: self::formatTimestamp($updatedAt),
            regarding: $regarding,
            owner: $owner,
        );
    }
}
