<?php

use App\Http\Controllers\Api\Controller;

it('respondAccepted returns 202 with message only when no jobId given', function () {
    $controller = new class extends Controller
    {
        public function testAccepted(?string $jobId = null): \Illuminate\Http\JsonResponse
        {
            return $this->respondAccepted($jobId);
        }
    };

    $response = $controller->testAccepted();

    expect($response->getStatusCode())->toBe(202);

    $data = $response->getData(true);
    expect($data)->toBe(['message' => 'Accepted']);
    expect($data)->not->toHaveKey('job_id');
});

it('respondAccepted returns 202 with job_id when jobId given', function () {
    $controller = new class extends Controller
    {
        public function testAccepted(?string $jobId = null): \Illuminate\Http\JsonResponse
        {
            return $this->respondAccepted($jobId);
        }
    };

    $response = $controller->testAccepted('batch-123');

    expect($response->getStatusCode())->toBe(202);

    $data = $response->getData(true);
    expect($data['message'])->toBe('Accepted');
    expect($data['job_id'])->toBe('batch-123');
});

it('respondWith wraps data with given key', function () {
    $controller = new class extends Controller
    {
        public function testWith(mixed $data, string $key): \Illuminate\Http\JsonResponse
        {
            return $this->respondWith($data, $key);
        }
    };

    $response = $controller->testWith(['id' => 1, 'name' => 'Test'], 'user');

    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toBe(['user' => ['id' => 1, 'name' => 'Test']]);
});

it('respondWithCollection includes meta without paginator', function () {
    $controller = new class extends Controller
    {
        public function testCollection(mixed $items, string $key): \Illuminate\Http\JsonResponse
        {
            return $this->respondWithCollection($items, $key);
        }
    };

    $items = [['id' => 1], ['id' => 2]];
    $response = $controller->testCollection($items, 'users');

    $data = $response->getData(true);
    expect($data['users'])->toHaveCount(2);
    expect($data['meta'])->toBe(['total' => 2, 'per_page' => 2, 'page' => 1]);
});

it('respondWithError returns error response', function () {
    $controller = new class extends Controller
    {
        public function testError(string $msg, int $status, ?array $errors = null): \Illuminate\Http\JsonResponse
        {
            return $this->respondWithError($msg, $status, $errors);
        }
    };

    $response = $controller->testError('Validation failed', 422, ['name' => ['Required']]);

    expect($response->getStatusCode())->toBe(422);

    $data = $response->getData(true);
    expect($data['message'])->toBe('Validation failed');
    expect($data['errors'])->toBe(['name' => ['Required']]);
});
