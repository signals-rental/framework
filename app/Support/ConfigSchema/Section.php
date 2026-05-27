<?php

namespace App\Support\ConfigSchema;

/**
 * A labelled {@see Schema} — one visual panel in a composed rate-definition form
 * (e.g. "Options" for the strategy, "Multipliers"/"Factors" for modifiers, or a
 * plugin modifier's own section).
 */
class Section
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly Schema $schema,
    ) {}

    /**
     * @return array{key: string, label: string, fields: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'fields' => $this->schema->toArray(),
        ];
    }
}
