<?php

use App\Services\ConnectionTesters\RedisConnectionTester;

it('returns failure shape on bad redis connection', function () {
    $tester = new RedisConnectionTester;

    $result = $tester->test([
        'host' => '127.0.0.1',
        'port' => 59999,
        'password' => null,
    ]);

    expect($result)->toHaveKeys(['success', 'version', 'error']);
    expect($result['success'])->toBeFalse();
    expect($result['version'])->toBeNull();
    expect($result['error'])->toBeString();
});

it('handles null password correctly', function () {
    $tester = new RedisConnectionTester;

    // Should not throw when password is null
    $result = $tester->test([
        'host' => '127.0.0.1',
        'port' => 59999,
        'password' => null,
    ]);

    expect($result['success'])->toBeFalse();
});

it('handles string null password correctly', function () {
    $tester = new RedisConnectionTester;

    $result = $tester->test([
        'host' => '127.0.0.1',
        'port' => 59999,
        'password' => 'null',
    ]);

    expect($result['success'])->toBeFalse();
});
