<?php

namespace App\Data\Activities;

use App\Data\Concerns\EntityReferenceData;
use App\Data\Concerns\FormatsTimestamps;
use App\Enums\ActivityPriority;
use App\Enums\ActivityStatus;
use App\Enums\TimeStatus;
use App\Models\Activity;
use App\Models\ActivityParticipant;
use App\Services\FileService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class ActivityData extends Data
{
    use FormatsTimestamps;

    /**
     * @param  int  $priority  Priority: 0=Low, 1=Normal, 2=High.
     * @param  int|null  $type_id  The 'Activity Type' list_values id (the editable list value). Null when the activity has no type (e.g. a legacy/dirty value was nulled, or its list value was deleted via nullOnDelete).
     * @param  int|null  $type_code  The RMS-aligned ActivityType code (Task=1001..Letter=1007). Null for user-added custom types that have no RMS code.
     * @param  int  $status_id  Status: 2001=Scheduled, 2002=Completed, 2003=Cancelled, 2004=Held.
     * @param  int  $time_status  Calendar busy state: 0=Free, 1=Busy.
     * @param  object{}  $custom_fields  Custom field values keyed by field name.
     * @param  list<ActivityParticipantData>  $participants
     * @param  string|null  $owner_thumb_url  Signed thumbnail URL for the owner's linked member, when one exists.
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
        public ?int $type_id,
        public ?int $type_code,
        public int $status_id,
        public bool $completed,
        public int $time_status,
        public object $custom_fields,
        public array $participants,
        public string $activity_type_name,
        public string $activity_status_name,
        public string $time_status_name,
        public string $created_at,
        public string $updated_at,
        public ?EntityReferenceData $regarding = null,
        public ?EntityReferenceData $owner = null,
        public ?string $owner_thumb_url = null,
    ) {}

    public static function fromModel(Activity $activity): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $activity->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $activity->updated_at;

        // Resolve the type list value (loaded lazily so activity_type_name and
        // type_code always populate, even when the caller did not eager-load
        // `type`). Built-in types carry the RMS code (1001-1007) in their
        // metadata; user-added custom types have none, so type_code is null.
        $activity->loadMissing('type');
        $type = $activity->relationLoaded('type') ? $activity->type : null;
        $typeName = $type !== null ? $type->name : '';
        $typeMetadata = $type !== null ? ($type->metadata ?? []) : [];
        $rawTypeCode = $typeMetadata['code'] ?? null;
        $typeCode = is_numeric($rawTypeCode) ? (int) $rawTypeCode : null;

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
            // Eager-load members in a single query to avoid an N+1 when serialising
            // each participant's member_name (whether or not participants.member was requested).
            $activity->participants->loadMissing('member');
            $participants = $activity->participants
                ->map(fn (ActivityParticipant $participant): ActivityParticipantData => ActivityParticipantData::fromModel($participant))
                ->all();
        }

        $regarding = null;
        if ($activity->relationLoaded('regarding') && $activity->regarding) {
            /** @var Model $regardingModel */
            $regardingModel = $activity->regarding;
            $regarding = EntityReferenceData::from([
                'id' => $regardingModel->getKey(),
                'name' => $regardingModel->getAttribute('name') ?? '',
            ]);
        }

        $owner = null;
        $ownerThumbUrl = null;
        if ($activity->relationLoaded('owner') && $activity->owner) {
            $owner = EntityReferenceData::from([
                'id' => $activity->owner->id,
                'name' => $activity->owner->name,
            ]);

            // Resolve the owner's linked-member thumbnail once here so consumers
            // (e.g. the calendar detail modal) read a signed URL off the DTO
            // instead of issuing a second User::with('member')->find() lookup.
            $activity->owner->loadMissing('member');
            $ownerThumbUrl = app(FileService::class)->signedUrlOrNull($activity->owner->member?->icon_thumb_url);
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
            type_id: $activity->type_id,
            type_code: $typeCode,
            status_id: $status->value,
            completed: $activity->completed,
            time_status: $timeStatus->value,
            custom_fields: (object) ($activity->relationLoaded('customFieldValues') ? $activity->custom_fields : []),
            participants: $participants,
            activity_type_name: $typeName,
            activity_status_name: $status->label(),
            time_status_name: $timeStatus->label(),
            created_at: self::formatTimestamp($createdAt),
            updated_at: self::formatTimestamp($updatedAt),
            regarding: $regarding,
            owner: $owner,
            owner_thumb_url: $ownerThumbUrl,
        );
    }
}
