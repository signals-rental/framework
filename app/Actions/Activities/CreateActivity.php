<?php

namespace App\Actions\Activities;

use App\Data\Activities\ActivityData;
use App\Data\Activities\CreateActivityData;
use App\Enums\ActivityType;
use App\Events\AuditableEvent;
use App\Models\Activity;
use App\Models\ListName;
use App\Models\ListValue;
use App\Services\Api\WebhookService;
use App\Services\CustomFieldValidator;
use Database\Seeders\ListOfValuesSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateActivity
{
    public function __invoke(CreateActivityData $data): ActivityData
    {
        Gate::authorize('activities.create');

        app(CustomFieldValidator::class)->validate('Activity', $data->custom_fields, enforceRequired: true);

        return DB::transaction(function () use ($data): ActivityData {
            $activity = Activity::create([
                'subject' => $data->subject,
                'description' => $data->description,
                'location' => $data->location,
                'regarding_id' => $data->regarding_id,
                'regarding_type' => Activity::resolveRegardingType($data->regarding_type),
                'owned_by' => $data->owned_by ?? auth()->id(),
                'starts_at' => $data->starts_at,
                'ends_at' => $data->ends_at,
                'priority' => $data->priority,
                'type_id' => $data->type_id ?? $this->defaultTypeId(),
                'status_id' => $data->status_id,
                'completed' => $data->completed,
                'time_status' => $data->time_status,
            ]);

            $activity->syncCustomFields($data->custom_fields, applyDefaults: true);

            if ($data->participants !== null) {
                foreach ($data->participants as $participant) {
                    $activity->participants()->create([
                        'member_id' => $participant['member_id'],
                        'mute' => $participant['mute'] ?? false,
                    ]);
                }
            }

            event(new AuditableEvent($activity, 'activity.created'));

            app(WebhookService::class)->dispatch('activity.created', [
                'activity' => ActivityData::fromModel($activity->load(['owner', 'participants.member', 'type']))->toArray(),
            ]);

            return ActivityData::fromModel($activity->load(['owner', 'participants.member', 'type']));
        });
    }

    /**
     * The "Activity Type" list's default (Task) value id, used when no type is
     * supplied.
     *
     * Anchored on the stable `metadata.icon` key ('task') seeded by
     * {@see ListOfValuesSeeder}, not the user-editable label,
     * so default selection survives an admin renaming the "Task" list value.
     * Falls back to the first active value if no Task value is found.
     */
    private function defaultTypeId(): ?int
    {
        $listId = ListName::query()->where('name', 'Activity Type')->value('id');

        if ($listId === null) {
            return null;
        }

        $values = ListValue::query()
            ->where('list_name_id', $listId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $default = $values->first(
            fn (ListValue $value): bool => ($value->metadata['icon'] ?? null) === ActivityType::Task->icon()
        ) ?? $values->first();

        return $default?->id !== null ? (int) $default->id : null;
    }
}
