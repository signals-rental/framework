<?php

use App\Models\Activity;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\StockLevel;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;

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

    it('returns empty results for user without permissions', function () {
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

    it('returns empty array when query is too short', function () {
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

    it('returns empty array when query is missing', function () {
        $this->actingAs($this->owner)
            ->getJson(route('search'))
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
                    '*' => ['id', 'name', 'type', 'typeValue', 'isActive', 'initials', 'icon', 'url'],
                ],
            ])
            ->assertJsonPath('members.0.initials', 'AC')
            ->assertJsonPath('members.0.typeValue', 'organisation')
            ->assertJsonPath('members.0.icon', null);
    })->skip(fn () => config('database.default') === 'sqlite', 'Search uses PostgreSQL ilike operator');

    it('includes a signed icon url for members and products with a profile image', function () {
        Storage::fake('public');

        Member::factory()->organisation()->create([
            'name' => 'Imaged Org',
            'icon_thumb_url' => 'icons/org-thumb.jpg',
        ]);
        Product::factory()->create([
            'name' => 'Imaged Org Product',
            'icon_thumb_url' => 'icons/product-thumb.jpg',
        ]);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'Imaged Org']))
            ->assertOk()
            ->assertJsonPath('members.0.icon', fn ($icon) => is_string($icon) && str_contains($icon, 'icons/org-thumb.jpg'))
            ->assertJsonPath('products.0.icon', fn ($icon) => is_string($icon) && str_contains($icon, 'icons/product-thumb.jpg'));
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

    it('returns empty arrays for all entity types when query is too short', function () {
        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'a']))
            ->assertOk()
            ->assertJsonStructure(['members', 'products', 'stock_levels', 'product_groups', 'activities', 'opportunities']);
    });

    it('returns matching products', function () {
        Product::factory()->create(['name' => 'LED Wash Light']);
        Product::factory()->create(['name' => 'Speaker System']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'LED']))
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.name', 'LED Wash Light');
    })->skip(fn () => config('database.default') === 'sqlite', 'Search uses PostgreSQL ilike operator');

    it('returns matching stock levels by asset number', function () {
        StockLevel::factory()->create(['asset_number' => '18670']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => '18670']))
            ->assertOk()
            ->assertJsonCount(1, 'stock_levels');
    })->skip(fn () => config('database.default') === 'sqlite', 'Search uses PostgreSQL ilike operator');

    it('returns matching product groups', function () {
        ProductGroup::factory()->create(['name' => 'Lighting Equipment']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'Lighting']))
            ->assertOk()
            ->assertJsonCount(1, 'product_groups')
            ->assertJsonPath('product_groups.0.name', 'Lighting Equipment');
    })->skip(fn () => config('database.default') === 'sqlite', 'Search uses PostgreSQL ilike operator');

    it('returns matching activities', function () {
        Activity::factory()->create(['subject' => 'Follow up on festival quote']);
        Activity::factory()->create(['subject' => 'Call supplier about cables']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'festival']))
            ->assertOk()
            ->assertJsonCount(1, 'activities')
            ->assertJsonPath('activities.0.name', 'Follow up on festival quote');
    })->skip(fn () => config('database.default') === 'sqlite', 'Search uses PostgreSQL ilike operator');

    it('returns activity results with correct shape', function () {
        Activity::factory()->create(['subject' => 'Test Activity']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'Test']))
            ->assertOk()
            ->assertJsonStructure([
                'activities' => [
                    '*' => ['id', 'name', 'type', 'typeValue', 'url'],
                ],
            ]);
    })->skip(fn () => config('database.default') === 'sqlite', 'Search uses PostgreSQL ilike operator');

    it('returns product results with correct shape', function () {
        Product::factory()->create(['name' => 'Test Product']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'Test']))
            ->assertOk()
            ->assertJsonStructure([
                'products' => [
                    '*' => ['id', 'name', 'type', 'typeValue', 'icon', 'url'],
                ],
            ]);
    })->skip(fn () => config('database.default') === 'sqlite', 'Search uses PostgreSQL ilike operator');

    it('returns matching opportunities by subject', function () {
        Opportunity::factory()->create(['subject' => 'Summer Festival Main Stage']);
        Opportunity::factory()->create(['subject' => 'Winter Gala Lighting']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'Festival']))
            ->assertOk()
            ->assertJsonCount(1, 'opportunities')
            ->assertJsonPath('opportunities.0.name', 'Summer Festival Main Stage');
    })->skip(fn () => config('database.default') === 'sqlite', 'Search uses PostgreSQL ilike operator');

    it('returns matching opportunities by number and reference', function () {
        Opportunity::factory()->create(['subject' => 'Numbered Deal', 'number' => 'OPP-00042', 'reference' => 'CUST-REF-XYZ']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'OPP-00042']))
            ->assertOk()
            ->assertJsonCount(1, 'opportunities');

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'CUST-REF']))
            ->assertOk()
            ->assertJsonCount(1, 'opportunities');
    })->skip(fn () => config('database.default') === 'sqlite', 'Search uses PostgreSQL ilike operator');

    it('returns opportunity results with correct shape including the state label', function () {
        Opportunity::factory()->order()->create(['subject' => 'Test Opportunity']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'Test']))
            ->assertOk()
            ->assertJsonStructure([
                'opportunities' => [
                    '*' => ['id', 'name', 'number', 'type', 'typeValue', 'url'],
                ],
            ])
            ->assertJsonPath('opportunities.0.typeValue', 'opportunity');
    })->skip(fn () => config('database.default') === 'sqlite', 'Search uses PostgreSQL ilike operator');

    it('excludes opportunities for a user without opportunities.view', function () {
        Opportunity::factory()->create(['subject' => 'Hidden Festival Deal']);

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('members.access', 'members.view');

        $this->actingAs($viewer)
            ->getJson(route('search', ['q' => 'Festival']))
            ->assertOk()
            ->assertJsonCount(0, 'opportunities');
    })->skip(fn () => config('database.default') === 'sqlite', 'Search uses PostgreSQL ilike operator');
});
