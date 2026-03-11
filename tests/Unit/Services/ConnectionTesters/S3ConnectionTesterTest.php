<?php

use App\Services\ConnectionTesters\S3ConnectionTester;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;

it('returns failure shape with error message for exception', function () {
    $tester = new S3ConnectionTester;

    // Test with intentionally bad config that will fail during client creation
    $result = $tester->test([
        'key' => '',
        'secret' => '',
        'region' => 'us-east-1',
        'bucket' => 'nonexistent-bucket-signals-test-xyz',
        'endpoint' => 'http://127.0.0.1:1',  // Non-routable endpoint
        'use_path_style' => false,
    ]);

    expect($result)->toHaveKeys(['success', 'error']);
    expect($result['success'])->toBeFalse();
    expect($result['error'])->toBeString();
});

it('returns correct result shape', function () {
    $tester = new S3ConnectionTester;

    $result = $tester->test([
        'key' => 'fake-key',
        'secret' => 'fake-secret',
        'region' => 'us-east-1',
        'bucket' => 'nonexistent-bucket-signals-test-xyz',
        'endpoint' => 'http://127.0.0.1:1',
        'use_path_style' => true,
    ]);

    expect($result)->toHaveKeys(['success', 'error']);
    expect($result['success'])->toBeFalse();
});

it('handles endpoint and path style config', function () {
    $tester = new S3ConnectionTester;

    // Both with and without endpoint should not crash
    $result1 = $tester->test([
        'key' => 'fake-key',
        'secret' => 'fake-secret',
        'region' => 'us-east-1',
        'bucket' => 'test-bucket',
        'endpoint' => null,
        'use_path_style' => false,
    ]);

    $result2 = $tester->test([
        'key' => 'fake-key',
        'secret' => 'fake-secret',
        'region' => 'us-east-1',
        'bucket' => 'test-bucket',
        'endpoint' => 'http://localhost:9000',
        'use_path_style' => true,
    ]);

    expect($result1)->toHaveKeys(['success', 'error']);
    expect($result2)->toHaveKeys(['success', 'error']);
});

// Mock-based tests that don't require a real S3 connection

it('returns success when put, get, verify, and delete all succeed via mock', function () {
    $capturedContent = null;

    $mockS3 = Mockery::mock(S3Client::class);
    $mockS3->shouldReceive('putObject')->once()->andReturnUsing(function ($args) use (&$capturedContent) {
        $capturedContent = $args['Body'];

        return new \Aws\Result([]);
    });
    $mockS3->shouldReceive('getObject')->once()->andReturnUsing(function () use (&$capturedContent) {
        return ['Body' => $capturedContent];
    });
    $mockS3->shouldReceive('deleteObject')->once();

    $tester = Mockery::mock(S3ConnectionTester::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $tester->shouldReceive('createClient')->andReturn($mockS3);

    $result = $tester->test([
        'key' => 'AKIATEST',
        'secret' => 'secret',
        'region' => 'us-east-1',
        'bucket' => 'test-bucket',
        'endpoint' => null,
        'use_path_style' => false,
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['error'])->toBeNull();
});

it('returns failure when read-back content does not match via mock', function () {
    $mockS3 = Mockery::mock(S3Client::class);
    $mockS3->shouldReceive('putObject')->once();
    $mockS3->shouldReceive('getObject')->once()->andReturn([
        'Body' => 'corrupted-content',
    ]);
    // deleteObject should NOT be called when content doesn't match
    $mockS3->shouldNotReceive('deleteObject');

    $tester = Mockery::mock(S3ConnectionTester::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $tester->shouldReceive('createClient')->andReturn($mockS3);

    $result = $tester->test([
        'key' => 'AKIATEST',
        'secret' => 'secret',
        'region' => 'us-east-1',
        'bucket' => 'test-bucket',
        'endpoint' => null,
        'use_path_style' => false,
    ]);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('read-back content did not match');
});

it('returns AWS error message when AwsException is thrown via mock', function () {
    $mockS3 = Mockery::mock(S3Client::class);
    $mockS3->shouldReceive('putObject')->once()->andThrow(
        new AwsException('Access Denied', Mockery::mock(\Aws\CommandInterface::class), [
            'message' => 'Access Denied',
        ])
    );

    $tester = Mockery::mock(S3ConnectionTester::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $tester->shouldReceive('createClient')->andReturn($mockS3);

    $result = $tester->test([
        'key' => 'AKIATEST',
        'secret' => 'secret',
        'region' => 'us-east-1',
        'bucket' => 'test-bucket',
        'endpoint' => null,
        'use_path_style' => false,
    ]);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toBeString();
});

it('returns generic error message when non-AWS exception is thrown via mock', function () {
    $mockS3 = Mockery::mock(S3Client::class);
    $mockS3->shouldReceive('putObject')->once()->andThrow(
        new \Exception('Network timeout')
    );

    $tester = Mockery::mock(S3ConnectionTester::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $tester->shouldReceive('createClient')->andReturn($mockS3);

    $result = $tester->test([
        'key' => 'AKIATEST',
        'secret' => 'secret',
        'region' => 'us-east-1',
        'bucket' => 'test-bucket',
        'endpoint' => null,
        'use_path_style' => false,
    ]);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toBe('Network timeout');
});
