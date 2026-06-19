<?php

namespace App\Data\Opportunities;

use App\Enums\VersionType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * Input DTO for creating a quote version (opportunity-lifecycle.md §8.6).
 *
 * `version_type` selects a sequential REVISION (supersedes its parent) or a
 * parallel ALTERNATIVE (coexists). `source_version_id` is the version whose line
 * items are cloned into the new version; when omitted the opportunity's current
 * active version is the source.
 */
class CreateVersionData extends Data
{
    public function __construct(
        public int $version_type = VersionType::Revision->value,
        public string|Optional|null $label = null,
        public int|Optional|null $source_version_id = null,
        public string|Optional|null $notes = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'version_type' => ['sometimes', 'integer', Rule::enum(VersionType::class)],
            'label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source_version_id' => ['sometimes', 'nullable', 'integer'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
