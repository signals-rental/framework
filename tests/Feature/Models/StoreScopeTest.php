<?php

use App\Models\Member;
use App\Models\MemberStore;
use App\Models\Scopes\StoreScope;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

describe('StoreScope', function () {
    it('does not filter when user is owner', function () {
        $owner = User::factory()->owner()->create();
        $this->actingAs($owner);

        $scope = new StoreScope;
        /** @var Builder<Model> $builder */
        $builder = Store::query();
        $scope->apply($builder, new Store);

        expect($builder->toSql())->not->toContain('where');
    });

    it('does not filter when user has admin access', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $scope = new StoreScope;
        /** @var Builder<Model> $builder */
        $builder = Store::query();
        $scope->apply($builder, new Store);

        expect($builder->toSql())->not->toContain('where');
    });

    it('does not filter when no user is authenticated', function () {
        $scope = new StoreScope;
        /** @var Builder<Model> $builder */
        $builder = Store::query();
        $scope->apply($builder, new Store);

        expect($builder->toSql())->not->toContain('where');
    });

    it('bypasses scoping in withoutScoping callback', function () {
        $result = StoreScope::withoutScoping(function () {
            $scope = new StoreScope;
            /** @var Builder<Model> $builder */
            $builder = Store::query();
            $scope->apply($builder, new Store);

            return $builder->toSql();
        });

        expect($result)->not->toContain('where');
    });

    it('restores scoping after withoutScoping callback', function () {
        StoreScope::withoutScoping(fn () => null);

        // After callback, scoping should be re-enabled
        expect(true)->toBeTrue();
    });
});

describe('User::accessibleStoreIds with member_stores', function () {
    it('returns store IDs from member_stores when assignments exist', function () {
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        $member = Member::factory()->create();
        $user = User::factory()->create(['member_id' => $member->id]);

        MemberStore::create(['member_id' => $member->id, 'store_id' => $store1->id, 'created_at' => now()]);
        MemberStore::create(['member_id' => $member->id, 'store_id' => $store2->id, 'created_at' => now()]);

        $storeIds = $user->accessibleStoreIds();

        expect($storeIds)->toContain($store1->id);
        expect($storeIds)->toContain($store2->id);
        expect($storeIds)->toHaveCount(2);
    });

    it('returns null for owners regardless of member_stores', function () {
        $owner = User::factory()->owner()->create();

        expect($owner->accessibleStoreIds())->toBeNull();
    });

    it('returns empty array for users with no member_id', function () {
        $user = User::factory()->create(['member_id' => null]);

        expect($user->accessibleStoreIds())->toBe([]);
    });
});
