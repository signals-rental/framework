<?php

use App\Livewire\Concerns\HandlesMergeErrors;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Host exposing the protected merge-error ladder so each catch arm can be
 * exercised in isolation. A named class (not anonymous) so the analyser resolves
 * the boolean return type of run() at the call sites below.
 */
class MergeErrorHost
{
    use HandlesMergeErrors;

    /**
     * @param  Closure(): mixed  $callback
     * @param  array<string, mixed>  $logContext
     */
    public function run(Closure $callback, string $entityLabel = 'member', array $logContext = []): bool
    {
        return $this->runGuardedMerge($callback, $entityLabel, $logContext);
    }
}

function mergeErrorHost(): MergeErrorHost
{
    return new MergeErrorHost;
}

it('returns true and flashes nothing when the merge callback succeeds', function () {
    $ran = false;
    $result = mergeErrorHost()->run(function () use (&$ran): void {
        $ran = true;
    });

    expect($result)->toBeTrue()
        ->and($ran)->toBeTrue()
        ->and(session()->has('error'))->toBeFalse();
});

it('flashes the validation message and returns false on a ValidationException', function () {
    $result = mergeErrorHost()->run(function (): void {
        throw ValidationException::withMessages(['secondary_id' => 'Cannot merge a contact into an organisation.']);
    });

    expect($result)->toBeFalse()
        ->and(session('error'))->toBe('Cannot merge a contact into an organisation.');
});

it('flashes a "no longer exists" message and returns false on a ModelNotFoundException', function () {
    // Exercises lines 41 + 43 of HandlesMergeErrors.
    $result = mergeErrorHost()->run(function (): void {
        throw new ModelNotFoundException;
    }, 'member');

    expect($result)->toBeFalse()
        ->and(session('error'))->toBe('One of the selected members no longer exists.');
});

it('logs and flashes a generic message and returns false on an unexpected Throwable', function () {
    $result = mergeErrorHost()->run(function (): void {
        throw new RuntimeException('boom');
    }, 'product');

    expect($result)->toBeFalse()
        ->and(session('error'))->toBe('An unexpected error occurred while merging. Please try again.');
});
