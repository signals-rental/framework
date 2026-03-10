<?php

use App\Models\User;
use App\Services\ConnectionTesters\PostgresConnectionTester;
use App\Services\ConnectionTesters\RedisConnectionTester;
use App\Services\ConnectionTesters\S3ConnectionTester;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

it('renders the infrastructure page for owner users', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->get(route('admin.settings.infrastructure'))
        ->assertOk()
        ->assertSee('Infrastructure')
        ->assertSee('Danger Zone');
});

it('returns 403 for admin users who are not owners', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.settings.infrastructure'))
        ->assertForbidden();
});

it('returns 403 for regular users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.settings.infrastructure'))
        ->assertForbidden();
});

it('loads current database configuration', function () {
    $owner = User::factory()->owner()->create();

    config([
        'database.connections.pgsql.host' => '10.0.0.1',
        'database.connections.pgsql.port' => 5433,
        'database.connections.pgsql.database' => 'my_db',
        'database.connections.pgsql.username' => 'my_user',
    ]);

    Volt::actingAs($owner)->test('admin.settings.infrastructure')
        ->assertSet('dbHost', '10.0.0.1')
        ->assertSet('dbPort', 5433)
        ->assertSet('dbDatabase', 'my_db')
        ->assertSet('dbUsername', 'my_user');
});

it('loads current redis configuration', function () {
    $owner = User::factory()->owner()->create();

    config([
        'database.redis.default.host' => '10.0.0.2',
        'database.redis.default.port' => 6380,
    ]);

    Volt::actingAs($owner)->test('admin.settings.infrastructure')
        ->assertSet('redisHost', '10.0.0.2')
        ->assertSet('redisPort', 6380);
});

it('loads current queue configuration', function () {
    $owner = User::factory()->owner()->create();

    config(['queue.default' => 'redis']);

    Volt::actingAs($owner)->test('admin.settings.infrastructure')
        ->assertSet('queueConnection', 'redis');
});

it('can test database connection', function () {
    $owner = User::factory()->owner()->create();

    $mock = mock(PostgresConnectionTester::class);
    $mock->shouldReceive('test')
        ->once()
        ->andReturn(['success' => true, 'version' => 'PostgreSQL 16.2', 'error' => null]);

    app()->instance(PostgresConnectionTester::class, $mock);

    Volt::actingAs($owner)->test('admin.settings.infrastructure')
        ->call('testDatabase')
        ->assertSet('dbTestResult.success', true)
        ->assertSet('dbTestResult.version', 'PostgreSQL 16.2');
});

it('can test redis connection', function () {
    $owner = User::factory()->owner()->create();

    $mock = mock(RedisConnectionTester::class);
    $mock->shouldReceive('test')
        ->once()
        ->andReturn(['success' => true, 'version' => 'Redis 7.2.4', 'error' => null]);

    app()->instance(RedisConnectionTester::class, $mock);

    Volt::actingAs($owner)->test('admin.settings.infrastructure')
        ->call('testRedis')
        ->assertSet('redisTestResult.success', true)
        ->assertSet('redisTestResult.version', 'Redis 7.2.4');
});

it('can test s3 connection', function () {
    $owner = User::factory()->owner()->create();

    $mock = mock(S3ConnectionTester::class);
    $mock->shouldReceive('test')
        ->once()
        ->andReturn(['success' => true, 'error' => null]);

    app()->instance(S3ConnectionTester::class, $mock);

    Volt::actingAs($owner)->test('admin.settings.infrastructure')
        ->call('testS3')
        ->assertSet('s3TestResult.success', true);
});

it('shows failed connection test result', function () {
    $owner = User::factory()->owner()->create();

    $mock = mock(PostgresConnectionTester::class);
    $mock->shouldReceive('test')
        ->once()
        ->andReturn(['success' => false, 'version' => null, 'error' => 'Connection refused']);

    app()->instance(PostgresConnectionTester::class, $mock);

    Volt::actingAs($owner)->test('admin.settings.infrastructure')
        ->call('testDatabase')
        ->assertSet('dbTestResult.success', false)
        ->assertSet('dbTestResult.error', 'Connection refused');
});

it('denies connection testing to non-owner admin', function () {
    $admin = User::factory()->admin()->create();

    Volt::actingAs($admin)->test('admin.settings.infrastructure')
        ->assertForbidden();
});

it('validates database fields when saving', function () {
    $owner = User::factory()->owner()->create();

    Volt::actingAs($owner)->test('admin.settings.infrastructure')
        ->set('dbHost', '')
        ->set('dbPort', 0)
        ->set('dbDatabase', '')
        ->call('saveDatabase')
        ->assertHasErrors(['dbHost', 'dbPort', 'dbDatabase']);
})->group('env-writing');

it('validates redis fields when saving', function () {
    $owner = User::factory()->owner()->create();

    Volt::actingAs($owner)->test('admin.settings.infrastructure')
        ->set('redisHost', '')
        ->set('redisPort', 0)
        ->call('saveRedis')
        ->assertHasErrors(['redisHost', 'redisPort']);
})->group('env-writing');

it('validates s3 fields when saving', function () {
    $owner = User::factory()->owner()->create();

    Volt::actingAs($owner)->test('admin.settings.infrastructure')
        ->set('s3Key', '')
        ->set('s3Secret', '')
        ->set('s3Region', '')
        ->set('s3Bucket', '')
        ->call('saveS3')
        ->assertHasErrors(['s3Key', 's3Secret', 's3Region', 's3Bucket']);
})->group('env-writing');

it('validates queue connection option', function () {
    $owner = User::factory()->owner()->create();

    Volt::actingAs($owner)->test('admin.settings.infrastructure')
        ->set('queueConnection', 'invalid')
        ->call('saveQueue')
        ->assertHasErrors(['queueConnection']);
})->group('env-writing');
