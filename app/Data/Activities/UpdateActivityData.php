<?php

namespace App\Data\Activities;

use App\Enums\ActivityPriority;
use App\Enums\ActivityStatus;
use App\Enums\TimeStatus;
use App\Models\Activity;
use App\Services\Activities\ActivityTypeList;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Data;

/**
 * Request payload for updating an activity. All properties are optional;
 * only provided fields are applied.
 */
class UpdateActivityData extends Data
{
    /**
     * @param  int|null  $type_id  The 'Activity Type' list_values id (custom list value); must reference a value in the 'Activity Type' list.
     * @param  list<array{member_id: int, mute?: bool}>|null  $participants
     * @param  array<string, mixed>|null  $custom_fields
     */
    public function __construct(
        public ?string $subject = null,
        public ?string $description = null,
        public ?string $location = null,
        public ?int $regarding_id = null,
        public ?string $regarding_type = null,
        public ?int $owned_by = null,
        public ?string $starts_at = null,
        public ?string $ends_at = null,
        public ?ActivityPriority $priority = null,
        public ?int $type_id = null,
        public ?ActivityStatus $status_id = null,
        public ?bool $completed = null,
        public ?TimeStatus $time_status = null,
        public ?array $participants = null,
        public ?array $custom_fields = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'regarding_id' => ['sometimes', 'nullable', 'required_with:regarding_type', 'integer'],
            'regarding_type' => ['sometimes', 'nullable', 'required_with:regarding_id', 'string', 'in:'.implode(',', array_keys(Activity::$regardingMap))],
            'owned_by' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'priority' => ['sometimes', new Enum(ActivityPriority::class)],
            'type_id' => self::typeIdRules(),
            'status_id' => ['sometimes', new Enum(ActivityStatus::class)],
            'completed' => ['sometimes', 'boolean'],
            'time_status' => ['sometimes', new Enum(TimeStatus::class)],
            'participants' => ['sometimes', 'nullable', 'array'],
            'participants.*.member_id' => ['required_with:participants', 'integer', 'exists:members,id'],
            'participants.*.mute' => ['sometimes', 'boolean'],
            'custom_fields' => ['sometimes', 'array'],
        ];
    }

    /**
     * Validation rules for the `type_id` field.
     *
     * The `exists` rule constrains the value to the "Activity Type" list. When
     * that list is unseeded (no list id), the `exists` rule is omitted so a
     * supplied type_id is not wrongly rejected — the database foreign key on
     * `activities.type_id` is the hard guard in that case.
     *
     * @return list<mixed>
     */
    private static function typeIdRules(): array
    {
        $rules = ['sometimes', 'nullable', 'integer'];

        $listId = app(ActivityTypeList::class)->listId();

        if ($listId !== null) {
            $rules[] = Rule::exists('list_values', 'id')->where('list_name_id', $listId);
        }

        return $rules;
    }
}
