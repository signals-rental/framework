<?php

namespace App\Guards\Opportunities;

/**
 * The structured outcome of a single guard stage (opportunity-lifecycle.md
 * §12.2). A stage either {@see allow()}s the transition to proceed to the next
 * stage, or {@see deny()}s it — at which point the pipeline stops and the action
 * surfaces the denial (the permission stage throws an authorization exception;
 * business rules throw a 422 ValidationException).
 *
 * Keeping the outcome a value object (rather than a bare bool/throw) lets the
 * pipeline report WHICH stage denied and carry a field-keyed message, while
 * letting individual stages still throw their native exception types where that
 * is the more faithful behaviour (e.g. permission → AuthorizationException).
 */
final readonly class GuardResult
{
    /**
     * @param  array<string, list<string>>  $errors  Validation-shaped errors for a denial (field → messages).
     */
    private function __construct(
        public bool $allowed,
        public ?string $stage = null,
        public array $errors = [],
    ) {}

    public static function allow(): self
    {
        return new self(allowed: true);
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function deny(string $stage, array $errors = []): self
    {
        return new self(allowed: false, stage: $stage, errors: $errors);
    }

    public function denied(): bool
    {
        return ! $this->allowed;
    }
}
