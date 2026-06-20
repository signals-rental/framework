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
 *
 * A denial also carries a machine-readable {@see $code} (e.g. `fx_tax_locked`,
 * `shortage_block`, `permission_denied`) so a consumer — notably the
 * `available_actions` endpoint — can branch on the REASON a transition is
 * blocked without parsing the human message: render an "Unlock rates" CTA on
 * `fx_tax_locked`, a "Resolve shortages" affordance on `shortage_block`, etc. The
 * code is a stable contract; the message is for humans.
 */
final readonly class GuardResult
{
    /**
     * @param  array<string, list<string>>  $errors  Validation-shaped errors for a denial (field → messages).
     * @param  string|null  $code  A stable, machine-readable denial reason (null on an allow).
     */
    private function __construct(
        public bool $allowed,
        public ?string $stage = null,
        public array $errors = [],
        public ?string $code = null,
    ) {}

    public static function allow(): self
    {
        return new self(allowed: true);
    }

    /**
     * @param  array<string, list<string>>  $errors
     * @param  string|null  $code  A stable machine-readable reason (e.g. `fx_tax_locked`).
     */
    public static function deny(string $stage, array $errors = [], ?string $code = null): self
    {
        return new self(allowed: false, stage: $stage, errors: $errors, code: $code);
    }

    public function denied(): bool
    {
        return ! $this->allowed;
    }

    /**
     * The first human-readable message across the denial's field errors, or a
     * generic fallback naming the stage. Null when the result is an allow.
     */
    public function firstError(): ?string
    {
        if ($this->allowed) {
            return null;
        }

        foreach ($this->errors as $messages) {
            if ($messages !== []) {
                return $messages[0];
            }
        }

        return "This transition was blocked at the {$this->stage} stage.";
    }
}
