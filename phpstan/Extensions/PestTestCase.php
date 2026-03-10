<?php

declare(strict_types=1);

namespace Signals\PHPStan;

use Closure;
use Illuminate\Testing\PendingCommand;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Virtual TestCase representing the effective API inside Pest test closures.
 *
 * Pest binds test closures with scope set to the TestCase class, making
 * protected methods (mock, partialMock, spy) callable. This class extends
 * Tests\TestCase and re-declares those methods as public so PHPStan
 * accepts the calls.
 *
 * @internal Only used for static analysis — never instantiated.
 */
class PestTestCase extends TestCase
{
    /**
     * @template TObject of object
     *
     * @param  class-string<TObject>  $abstract
     * @return MockInterface&TObject
     */
    public function mock($abstract, ?Closure $mock = null)
    {
        return parent::mock($abstract, $mock);
    }

    /**
     * @template TObject of object
     *
     * @param  class-string<TObject>  $abstract
     * @return MockInterface&TObject
     */
    public function partialMock($abstract, ?Closure $mock = null)
    {
        return parent::partialMock($abstract, $mock);
    }

    /**
     * @template TObject of object
     *
     * @param  class-string<TObject>  $abstract
     * @return MockInterface&TObject
     */
    public function spy($abstract, ?Closure $mock = null)
    {
        return parent::spy($abstract, $mock);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model|string  $table
     * @param  array<string, mixed>  $data
     */
    public function assertDatabaseHas($table, array $data, ?string $connection = null): static
    {
        return parent::assertDatabaseHas($table, $data, $connection);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model|string  $table
     * @param  array<string, mixed>  $data
     */
    public function assertDatabaseMissing($table, array $data, ?string $connection = null): static
    {
        return parent::assertDatabaseMissing($table, $data, $connection);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function artisan(string $command, array $arguments = []): PendingCommand
    {
        return parent::artisan($command, $arguments);
    }

    public function seed(mixed ...$class): static
    {
        return parent::seed(...$class);
    }
}
