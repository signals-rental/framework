<?php

namespace App\Data\Activities;

use App\Enums\ActivityPriority;
use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\TimeStatus;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Data;

class UpdateActivityData extends Data
{
    /**
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
        public ?int $priority = null,
        public ?int $type_id = null,
        public ?int $status_id = null,
        public ?bool $completed = null,
        public ?int $time_status = null,
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
            'regarding_type' => ['sometimes', 'nullable', 'required_with:regarding_id', 'string', 'in:Member,Product,StockLevel'],
            'owned_by' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'priority' => ['sometimes', 'integer', new Enum(ActivityPriority::class)],
            'type_id' => ['sometimes', 'integer', new Enum(ActivityType::class)],
            'status_id' => ['sometimes', 'integer', new Enum(ActivityStatus::class)],
            'completed' => ['sometimes', 'boolean'],
            'time_status' => ['sometimes', 'integer', new Enum(TimeStatus::class)],
            'participants' => ['sometimes', 'nullable', 'array'],
            'participants.*.member_id' => ['required_with:participants', 'integer', 'exists:members,id'],
            'participants.*.mute' => ['sometimes', 'boolean'],
            'custom_fields' => ['sometimes', 'array'],
        ];
    }
}
