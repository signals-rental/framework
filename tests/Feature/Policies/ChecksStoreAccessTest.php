<?php

use App\Models\User;
use App\Policies\Traits\ChecksStoreAccess;
use Illuminate\Database\Eloquent\Model;

class TestStorePolicy
{
    use ChecksStoreAccess;

    public function check(User $user, Model $model): bool
    {
        return $this->canAccessStore($user, $model);
    }
}

beforeEach(function () {
    $this->policy = new TestStorePolicy;
});

function makeModelWithStoreId(?int $storeId): Model
{
    $model = new class extends Model
    {
        protected $guarded = [];
    };

    if ($storeId !== null) {
        $model->forceFill(['store_id' => $storeId]);
    }

    return $model;
}

it('grants access to owner users regardless of store', function () {
    $user = User::factory()->owner()->create();
    $model = makeModelWithStoreId(5);

    expect($this->policy->check($user, $model))->toBeTrue();
});

it('grants access when model has no store_id attribute', function () {
    $user = User::factory()->create();

    $model = new class extends Model
    {
        protected $guarded = [];
    };

    expect($this->policy->check($user, $model))->toBeTrue();
});

it('grants access when model store_id is null', function () {
    $user = User::factory()->create();
    $model = makeModelWithStoreId(null);

    expect($this->policy->check($user, $model))->toBeTrue();
});

it('grants access when user has access to the model store', function () {
    $user = Mockery::mock(User::factory()->create())->makePartial();
    $user->shouldReceive('accessibleStoreIds')->andReturn([1, 2, 3]);

    $model = makeModelWithStoreId(2);

    expect($this->policy->check($user, $model))->toBeTrue();
});

it('denies access when user cannot access the model store', function () {
    $user = Mockery::mock(User::factory()->create())->makePartial();
    $user->shouldReceive('accessibleStoreIds')->andReturn([1, 2, 3]);

    $model = makeModelWithStoreId(99);

    expect($this->policy->check($user, $model))->toBeFalse();
});

it('denies access when user has no store access at all', function () {
    $user = Mockery::mock(User::factory()->create())->makePartial();
    $user->shouldReceive('accessibleStoreIds')->andReturn([]);

    $model = makeModelWithStoreId(1);

    expect($this->policy->check($user, $model))->toBeFalse();
});
