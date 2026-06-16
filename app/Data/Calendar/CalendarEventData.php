<?php

namespace App\Data\Calendar;

use App\Enums\ActivityPriority;
use App\Enums\ActivityStatus;
use App\Enums\TimeStatus;
use App\Models\Activity;
use App\Models\User;
use App\Services\Calendar\OwnerColorResolver;
use App\Support\Calendar\AllDayDetector;
use App\Support\Timezone;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

class CalendarEventData extends Data
{
    public function __construct(
        public int $id,
        public string $subject,
        public int $owner_id,
        public string $owner_name,
        public string $owner_initials,
        public string $owner_color,
        public ?string $starts_at,
        public ?string $ends_at,
        public bool $all_day,
        public int $type_id,
        public string $type_name,
        public string $type_icon,
        public int $status_id,
        public string $status_name,
        public bool $completed,
        public ?string $location,
        public int $time_status,
        public ?string $regarding_type,
        public ?int $regarding_id,
        public ?string $regarding_name,
        public bool $is_multi_day,
        public int $priority,
        /** @var list<array{user_id: int|null, member_id: int, name: string}> */
        public array $participants = [],
    ) {}

    /**
     * Build display-ready calendar event data from an Activity.
     *
     * Assumes the `owner` relationship is eager-loaded by the calling service;
     * degrades gracefully (name/initials fall back to empty strings) if it is
     * not. Timestamps are emitted as ISO 8601 strings (or null). The raw
     * `ends_at` is preserved — the D9 30-minute default is applied by the
     * rendering layer, not here.
     */
    public static function fromModel(Activity $activity): self
    {
        // Activity type now resolves through the "Activity Type" list of values.
        $type = $activity->relationLoaded('type') ? $activity->type : null;

        /** @var ActivityStatus $status */
        $status = $activity->status_id;

        /** @var TimeStatus $timeStatus */
        $timeStatus = $activity->time_status;

        /** @var ActivityPriority $priority */
        $priority = $activity->priority;

        // Display in the company timezone (stored in UTC).
        $timezone = app(Timezone::class);

        /** @var CarbonInterface|null $startsAt */
        $startsAt = $activity->starts_at !== null ? $timezone->toLocal($activity->starts_at) : null;

        /** @var CarbonInterface|null $endsAt */
        $endsAt = $activity->ends_at !== null ? $timezone->toLocal($activity->ends_at) : null;

        /** @var User|null $owner */
        $owner = $activity->relationLoaded('owner') ? $activity->owner : null;

        $ownerName = '';
        $ownerInitials = '';
        if ($owner !== null) {
            $ownerName = $owner->name;
            $ownerInitials = $owner->initials();
        }

        $regarding = $activity->relationLoaded('regarding') ? $activity->regarding : null;
        $regardingNameRaw = $regarding?->getAttribute('name');
        $regardingName = is_string($regardingNameRaw) ? $regardingNameRaw : null;

        $typeId = (int) $activity->type_id;
        $typeName = '';
        $typeIcon = 'task';
        if ($type !== null) {
            $typeId = $type->id;
            $typeName = $type->name;
            $metadata = $type->getAttribute('metadata');
            if (is_array($metadata) && isset($metadata['icon']) && is_string($metadata['icon'])) {
                $typeIcon = $metadata['icon'];
            }
        }

        $isMultiDay = ! AllDayDetector::isAllDay($startsAt, $endsAt)
            && $startsAt !== null
            && $endsAt !== null
            && ! $startsAt->isSameDay($endsAt);

        // Resolve participants to their staff (User) identity where possible so
        // the diary shows the same names the picker offered (a user's linked
        // member can carry a different display name in seed data).
        $participants = [];
        if ($activity->relationLoaded('participants')) {
            foreach ($activity->participants as $participant) {
                $member = $participant->relationLoaded('member') ? $participant->member : null;
                $user = ($member !== null && $member->relationLoaded('user')) ? $member->user : null;

                $name = 'Unknown';
                if ($user !== null) {
                    $name = $user->name;
                } elseif ($member !== null) {
                    $name = $member->name;
                }

                $participants[] = [
                    'user_id' => $user !== null ? $user->id : null,
                    'member_id' => (int) $participant->member_id,
                    'name' => $name,
                ];
            }
        }

        return new self(
            id: $activity->id,
            subject: $activity->subject,
            owner_id: $activity->owned_by,
            owner_name: $ownerName,
            owner_initials: $ownerInitials,
            owner_color: app(OwnerColorResolver::class)->for($activity->owned_by),
            starts_at: $startsAt?->toIso8601String(),
            ends_at: $endsAt?->toIso8601String(),
            all_day: AllDayDetector::isAllDay($startsAt, $endsAt),
            type_id: $typeId,
            type_name: $typeName,
            type_icon: $typeIcon,
            status_id: $status->value,
            status_name: $status->label(),
            completed: $activity->completed,
            location: $activity->location,
            time_status: $timeStatus->value,
            regarding_type: Activity::shortRegardingType($activity->regarding_type),
            regarding_id: $activity->regarding_id,
            regarding_name: $regardingName,
            is_multi_day: $isMultiDay,
            priority: $priority->value,
            participants: $participants,
        );
    }
}
