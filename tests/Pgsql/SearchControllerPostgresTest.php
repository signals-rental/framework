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
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL global search lane (SearchController)
|--------------------------------------------------------------------------
|
| SearchController queries every entity type with PostgreSQL's case-insensitive
| `ilike` operator. SQLite does not support `ilike`, so substantive search
| behaviour lives in this lane. The framework plan describes a future tsvector/GIN
| search_index model; the current controller is ilike-based only.
|
|   php artisan test --compact --group=pgsql
|
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('GET /search (PostgreSQL ilike)', function () {
    it('returns matching members with initials and type metadata', function () {
        Member::factory()->contact()->create(['name' => 'Alice Johnson']);
        Member::factory()->contact()->create(['name' => 'Bob Smith']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'alice']))
            ->assertOk()
            ->assertJsonCount(1, 'members')
            ->assertJsonPath('members.0.name', 'Alice Johnson')
            ->assertJsonPath('members.0.initials', 'AJ')
            ->assertJsonPath('members.0.typeValue', 'contact');
    });

    it('returns the expected member response shape including a null icon fallback', function () {
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
            ->assertJsonPath('members.0.icon', null);
    });

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
    });

    it('limits member results to eight', function () {
        Member::factory()->contact()->count(10)->create(['name' => 'Test User']);

        $response = $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'Test']))
            ->assertOk();

        expect($response->json('members'))->toHaveCount(8);
    });

    it('escapes ilike percent wildcards in the query', function () {
        Member::factory()->contact()->create(['name' => '100% Discount Member']);
        Member::factory()->contact()->create(['name' => 'Normal Member']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => '100%']))
            ->assertOk()
            ->assertJsonCount(1, 'members')
            ->assertJsonPath('members.0.name', '100% Discount Member');
    });

    it('escapes ilike underscore wildcards in the query', function () {
        Member::factory()->contact()->create(['name' => 'A_B Member']);
        Member::factory()->contact()->create(['name' => 'AXB Member']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'A_B']))
            ->assertOk()
            ->assertJsonCount(1, 'members')
            ->assertJsonPath('members.0.name', 'A_B Member');
    });

    it('performs case-insensitive search', function () {
        Member::factory()->contact()->create(['name' => 'Jane Doe']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'JANE']))
            ->assertOk()
            ->assertJsonCount(1, 'members');
    });

    it('derives single-word member initials from the first character only', function () {
        Member::factory()->contact()->create(['name' => 'Madonna']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'Madonna']))
            ->assertOk()
            ->assertJsonPath('members.0.initials', 'M');
    });

    it('returns matching products with the product response shape', function () {
        Product::factory()->create(['name' => 'LED Wash Light']);
        Product::factory()->create(['name' => 'Speaker System']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'LED']))
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.name', 'LED Wash Light')
            ->assertJsonStructure([
                'products' => [
                    '*' => ['id', 'name', 'type', 'typeValue', 'icon', 'url'],
                ],
            ]);
    });

    it('returns matching stock levels by asset number with a fallback display name', function () {
        StockLevel::factory()->create([
            'asset_number' => '18670',
            'item_name' => null,
        ]);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => '18670']))
            ->assertOk()
            ->assertJsonCount(1, 'stock_levels')
            ->assertJsonPath('stock_levels.0.name', 'Asset #18670')
            ->assertJsonPath('stock_levels.0.typeValue', 'stock_level');
    });

    it('returns matching product groups', function () {
        ProductGroup::factory()->create(['name' => 'Lighting Equipment']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'Lighting']))
            ->assertOk()
            ->assertJsonCount(1, 'product_groups')
            ->assertJsonPath('product_groups.0.name', 'Lighting Equipment')
            ->assertJsonPath('product_groups.0.typeValue', 'product_group');
    });

    it('returns matching activities with the activity type label', function () {
        Activity::factory()->create(['subject' => 'Follow up on festival quote']);
        Activity::factory()->create(['subject' => 'Call supplier about cables']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'festival']))
            ->assertOk()
            ->assertJsonCount(1, 'activities')
            ->assertJsonPath('activities.0.name', 'Follow up on festival quote')
            ->assertJsonStructure([
                'activities' => [
                    '*' => ['id', 'name', 'type', 'typeValue', 'url'],
                ],
            ]);
    });

    it('returns matching opportunities by subject, number, and reference', function () {
        Opportunity::factory()->create(['subject' => 'Summer Festival Main Stage']);
        Opportunity::factory()->create(['subject' => 'Winter Gala Lighting']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'Festival']))
            ->assertOk()
            ->assertJsonCount(1, 'opportunities')
            ->assertJsonPath('opportunities.0.name', 'Summer Festival Main Stage');

        Opportunity::factory()->create(['subject' => 'Numbered Deal', 'number' => 'OPP-00042', 'reference' => 'CUST-REF-XYZ']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'OPP-00042']))
            ->assertOk()
            ->assertJsonCount(1, 'opportunities');

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'CUST-REF']))
            ->assertOk()
            ->assertJsonCount(1, 'opportunities');
    });

    it('returns opportunity results with the state label and route url', function () {
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
    });

    it('excludes soft-deleted opportunities from search results', function () {
        $live = Opportunity::factory()->create(['subject' => 'Visible Festival']);
        $trashed = Opportunity::factory()->create(['subject' => 'Hidden Festival']);
        $trashed->delete();

        $response = $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'Festival']))
            ->assertOk()
            ->assertJsonCount(1, 'opportunities');

        expect($response->json('opportunities.0.id'))->toBe($live->id);
    });

    it('scopes results to the actor permissions', function () {
        Opportunity::factory()->create(['subject' => 'Hidden Festival Deal']);
        Member::factory()->contact()->create(['name' => 'Festival Contact']);

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('members.access', 'members.view');

        $this->actingAs($viewer)
            ->getJson(route('search', ['q' => 'Festival']))
            ->assertOk()
            ->assertJsonCount(0, 'opportunities')
            ->assertJsonCount(1, 'members')
            ->assertJsonPath('members.0.name', 'Festival Contact');
    });

    it('accepts a trimmed query of at least two characters', function () {
        Member::factory()->contact()->create(['name' => 'Trimmed Match']);

        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => '  Trimmed  ']))
            ->assertOk()
            ->assertJsonCount(1, 'members');
    });
});
