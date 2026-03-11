<?php

namespace App\Data\Api;

use App\Models\ActionLog;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class ActionLogData extends Data
{
    /**
     * @param  array<string, mixed>|null  $old_values
     * @param  array<string, mixed>|null  $new_values
     */
    public function __construct(
        public int $id,
        public ?int $user_id,
        public string $action,
        public ?string $auditable_type,
        public ?int $auditable_id,
        public ?array $old_values,
        public ?array $new_values,
        public ?string $ip_address,
        public ?string $created_at,
        public ?string $user_name = null,
    ) {}

    public static function fromModel(ActionLog $log): self
    {
        /** @var Carbon|null $createdAt */
        $createdAt = $log->created_at;

        /** @var array<string, mixed>|null $oldValues */
        $oldValues = $log->old_values;

        /** @var array<string, mixed>|null $newValues */
        $newValues = $log->new_values;

        return new self(
            id: $log->id,
            user_id: $log->user_id,
            action: $log->action,
            auditable_type: self::friendlyType($log->auditable_type),
            auditable_id: $log->auditable_id,
            old_values: $oldValues,
            new_values: $newValues,
            ip_address: $log->ip_address,
            created_at: $createdAt?->toIso8601String(),
            user_name: $log->user?->name,
        );
    }

    /**
     * Convert a FQCN to a friendly resource name (e.g. App\Models\User → user).
     */
    private static function friendlyType(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        $basename = class_basename($type);

        return str($basename)->snake()->toString();
    }
}
