<?php

namespace App\Data\Api;

use App\Models\ActionLog;
use Illuminate\Support\Carbon;

class ActionLogData
{
    /**
     * @param  array<string, mixed>|null  $old_values
     * @param  array<string, mixed>|null  $new_values
     */
    public function __construct(
        public readonly int $id,
        public readonly ?int $user_id,
        public readonly string $action,
        public readonly ?string $auditable_type,
        public readonly ?int $auditable_id,
        public readonly ?array $old_values,
        public readonly ?array $new_values,
        public readonly ?string $ip_address,
        public readonly ?string $created_at,
        public readonly ?string $user_name = null,
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
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'action' => $this->action,
            'auditable_type' => $this->auditable_type,
            'auditable_id' => $this->auditable_id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at,
            'user_name' => $this->user_name,
        ];
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
