<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| .env Crash Recovery
|--------------------------------------------------------------------------
|
| If a previous test run was interrupted (Ctrl+C, crash) while env-writing
| tests were active, the .env file may have been left in a modified state.
| Restore it from the backup created by the env-writing test group.
|
*/

$envBackup = dirname(__DIR__).'/.env.test-backup';
if (file_exists($envBackup)) {
    file_put_contents(dirname(__DIR__).'/.env', file_get_contents($envBackup));
    unlink($envBackup);
}

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
| The PostgreSQL lane (tests/Pgsql) runs against a real Postgres connection and
| manages its own schema + per-test transaction via the UsesPostgres trait, so
| it must NOT use the sqlite-targeted RefreshDatabase trait. It is bound to the
| base TestCase only and tagged `pgsql` so it skips cleanly when Postgres is
| unreachable (and so the default suite can exclude it).
*/
pest()->extend(TestCase::class)
    ->in('Pgsql');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something(): void
{
    // ..
}

/**
 * Assert that the AuditableEvent → LogAction listener wrote an action_logs row
 * end-to-end for the given action against the given model.
 *
 * Unlike the per-action tests that fake AuditableEvent (and so only prove the
 * event fired), callers of this helper must NOT fake AuditableEvent so the
 * auto-discovered LogAction listener runs and persists the row.
 *
 * @param  class-string<Model>  $auditableType
 */
function assertActionLogged(string $action, string $auditableType, int $auditableId, ?int $userId = null): void
{
    $expected = [
        'action' => $action,
        'auditable_type' => $auditableType,
        'auditable_id' => $auditableId,
    ];

    if ($userId !== null) {
        $expected['user_id'] = $userId;
    }

    \Pest\Laravel\assertDatabaseHas('action_logs', $expected);
}

/**
 * Capture session flash payloads during Livewire/Volt actions.
 *
 * Livewire clears `_flash.new` after non-redirect component updates, so
 * `session('key')` is null once the testable returns even though the
 * component did flash during the request.
 *
 * @return array<string, mixed>
 */
function captureFlashedMessages(callable $callback): array
{
    $session = app('session.store');
    $captured = [];

    $mock = Mockery::mock($session)->makePartial();
    $mock->shouldReceive('flash')->withArgs(function (mixed $key, mixed $value = true) use (&$captured): bool {
        $captured[(string) $key] = $value;

        return true;
    })->passthru();

    app()->instance('session.store', $mock);
    app()->instance('session', $mock);

    try {
        $callback();

        return $captured;
    } finally {
        app()->instance('session.store', $session);
        app()->instance('session', $session);
    }
}
