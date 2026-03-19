<?php

use App\Models\Member;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('GET /search', function () {
    it('requires authentication', function () {
        $this->getJson(route('search', ['q' => 'test']))
            ->assertUnauthorized();
    });

    it('requires members.view permission', function () {
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->getJson(route('search', ['q' => 'test']))
            ->assertForbidden();
    });

    it('returns empty array when query is too short', function () {
        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'a']))
            ->assertOk()
            ->assertExactJson(['members' => []]);
    });

    it('returns empty array when query is missing', function () {
        $this->actingAs($this->owner)
            ->getJson(route('search'))
            ->assertOk()
            ->assertExactJson(['members' => []]);
    });

    it('returns matching members', function () {
        Member::factory()->contact()->create(['name' => 'Alice Johnson']);
        Member::factory()->contact()->create(['name' => 'Bob Smith']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'alice']))
            ->assertOk()
            ->assertJsonCount(1, 'members')
            ->assertJsonPath('members.0.name', 'Alice Johnson');
    })->skip(fn () => config('database.default') === 'sqlite', 'Search uses PostgreSQL ilike operator');

    it('returns correct response shape', function () {
        Member::factory()->organisation()->create(['name' => 'Acme Corp']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'Acme']))
            ->assertOk()
            ->assertJsonStructure([
                'members' => [
                    '*' => ['id', 'name', 'type', 'typeValue', 'isActive', 'initials', 'url'],
                ],
            ])
            ->assertJsonPath('members.0.initials', 'AC')
            ->assertJsonPath('members.0.typeValue', 'organisation');
    })->skip(fn () => config('database.default') === 'sqlite', 'Search uses PostgreSQL ilike operator');

    it('limits results to 8', function () {
        Member::factory()->contact()->count(10)->create(['name' => 'Test User']);

        $response = $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'Test']))
            ->assertOk();

        expect($response->json('members'))->toHaveCount(8);
    })->skip(fn () => config('database.default') === 'sqlite', 'Search uses PostgreSQL ilike operator');

    it('escapes ILIKE wildcard characters', function () {
        Member::factory()->contact()->create(['name' => '100% Discount Member']);
        Member::factory()->contact()->create(['name' => 'Normal Member']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => '100%']))
            ->assertOk()
            ->assertJsonCount(1, 'members')
            ->assertJsonPath('members.0.name', '100% Discount Member');
    })->skip(fn () => config('database.default') === 'sqlite', 'Search uses PostgreSQL ilike operator');

    it('performs case-insensitive search', function () {
        Member::factory()->contact()->create(['name' => 'Jane Doe']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'JANE']))
            ->assertOk()
            ->assertJsonCount(1, 'members');
    })->skip(fn () => config('database.default') === 'sqlite', 'Search uses PostgreSQL ilike operator');
});
