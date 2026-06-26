<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

/*
| Substantive global-search behaviour (PostgreSQL `ilike` queries for every entity
| type) lives in tests/Pgsql/SearchControllerPostgresTest.php. This file covers
| only the SQLite-safe paths: auth, short-query early return, and permission gates
| that never reach the database search queries.
*/

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('GET /search (SQLite-safe paths)', function () {
    it('requires authentication', function () {
        $this->getJson(route('search', ['q' => 'test']))
            ->assertUnauthorized();
    });

    it('returns empty results for a user without any search permissions', function () {
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->getJson(route('search', ['q' => 'test']))
            ->assertOk()
            ->assertExactJson([
                'members' => [],
                'products' => [],
                'stock_levels' => [],
                'product_groups' => [],
                'activities' => [],
                'opportunities' => [],
            ]);
    });

    it('returns empty arrays when the query is too short', function () {
        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'a']))
            ->assertOk()
            ->assertExactJson([
                'members' => [],
                'products' => [],
                'stock_levels' => [],
                'product_groups' => [],
                'activities' => [],
                'opportunities' => [],
            ]);
    });

    it('returns empty arrays when the query parameter is missing', function () {
        $this->actingAs($this->owner)
            ->getJson(route('search'))
            ->assertOk()
            ->assertJsonStructure([
                'members',
                'products',
                'stock_levels',
                'product_groups',
                'activities',
                'opportunities',
            ])
            ->assertJsonCount(0, 'members')
            ->assertJsonCount(0, 'products')
            ->assertJsonCount(0, 'stock_levels')
            ->assertJsonCount(0, 'product_groups')
            ->assertJsonCount(0, 'activities')
            ->assertJsonCount(0, 'opportunities');
    });

    it('returns empty arrays for whitespace-only queries treated as too short', function () {
        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => '   ']))
            ->assertOk()
            ->assertJsonCount(0, 'members');
    });
});
