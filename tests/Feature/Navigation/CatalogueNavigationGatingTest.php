<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Catalogue (Products / Product Groups / Stock Levels)
|--------------------------------------------------------------------------
|
| The Resources mega-dropdown and the mobile "Catalogue" sidebar section
| both render Products + Product Groups (gated on products.access) and
| Stock Levels (gated on stock.access). The whole group is wrapped in
| @canany(['products.access', 'stock.access']), mirroring the CRM exemplar.
|
*/

describe('Catalogue nav group label gating', function () {
    it('shows the Catalogue group label to a user with products.access', function () {
        $user = User::factory()->create();
        $user->givePermissionTo(['products.access', 'products.view']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Catalogue', false);
    });

    it('shows the Catalogue group label to a user with only stock.access', function () {
        $user = User::factory()->create();
        $user->givePermissionTo(['stock.access', 'stock.view']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Catalogue', false);
    });

    it('hides the Catalogue group label from a user with neither catalogue permission', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Catalogue', false);
    });
});

/*
| route('products.index') / route('stock-levels.index') etc. also appear in
| the always-present command palette (out of scope for this fix), so the
| header/sidebar gating is asserted against nav-only marker strings: the
| mega-item descriptions, each of which occurs exactly once inside the gated
| Catalogue dropdown column.
*/
$productsNav = 'Equipment, services &amp; consumables';
$productGroupsNav = 'Categories &amp; hierarchy';
$stockLevelsNav = 'Inventory &amp; asset tracking';

describe('Products + Product Groups nav gating on products.access', function () use ($productsNav, $productGroupsNav, $stockLevelsNav) {
    it('shows the Products and Product Groups links to a user with products.access', function () use ($productsNav, $productGroupsNav) {
        $user = User::factory()->create();
        $user->givePermissionTo(['products.access', 'products.view']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($productsNav, false)
            ->assertSee($productGroupsNav, false);
    });

    it('hides the Products and Product Groups links from a user without products.access', function () use ($productsNav, $productGroupsNav) {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee($productsNav, false)
            ->assertDontSee($productGroupsNav, false);
    });

    it('hides the Products links from a stock-only user without products.access', function () use ($productsNav, $productGroupsNav, $stockLevelsNav) {
        $user = User::factory()->create();
        $user->givePermissionTo(['stock.access', 'stock.view']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            // The Catalogue group is visible (stock.access) but the products
            // column is gated separately and must not leak in.
            ->assertSee('Catalogue', false)
            ->assertDontSee($productsNav, false)
            ->assertDontSee($productGroupsNav, false)
            ->assertSee($stockLevelsNav, false);
    });
});

describe('Stock Levels nav gating on stock.access', function () use ($productsNav, $stockLevelsNav) {
    it('shows the Stock Levels link to a user with stock.access', function () use ($stockLevelsNav) {
        $user = User::factory()->create();
        $user->givePermissionTo(['stock.access', 'stock.view']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($stockLevelsNav, false);
    });

    it('hides the Stock Levels link from a user without stock.access', function () use ($productsNav, $stockLevelsNav) {
        $user = User::factory()->create();
        $user->givePermissionTo(['products.access', 'products.view']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            // Catalogue group visible (products.access) but stock column gated out.
            ->assertSee('Catalogue', false)
            ->assertSee($productsNav, false)
            ->assertDontSee($stockLevelsNav, false);
    });

    it('hides the Stock Levels link from a user with no catalogue permission', function () use ($stockLevelsNav) {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee($stockLevelsNav, false);
    });
});

/*
|--------------------------------------------------------------------------
| Tax admin nav link (gated on tax-classes.view)
|--------------------------------------------------------------------------
|
| The Tax link lives in the admin sidebar (admin.* area). It is reachable
| only by users with admin access (is_admin / is_owner / Admin role), and
| is now gated on tax-classes.view to mirror the Pricing link's rates.view
| gate. Owners bypass all gates, so the deny path is exercised with an
| is_admin (non-owner) user, who is subject to permission checks.
|
*/

describe('Tax admin nav gating on tax-classes.view', function () {
    $taxRoute = function () {
        return route('admin.settings.tax.product-tax-classes');
    };

    it('shows the Tax admin link to an admin with tax-classes.view', function () use ($taxRoute) {
        $user = User::factory()->admin()->create();
        $user->givePermissionTo(['settings.access', 'tax-classes.view']);

        $this->actingAs($user)
            ->get(route('admin.settings.api'))
            ->assertOk()
            ->assertSee($taxRoute(), false);
    });

    it('shows the Tax admin link to the owner (gate bypass)', function () use ($taxRoute) {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->get(route('admin.settings.api'))
            ->assertOk()
            ->assertSee($taxRoute(), false);
    });

    it('hides the Tax admin link from an admin without tax-classes.view', function () use ($taxRoute) {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('admin.settings.api'))
            ->assertOk()
            ->assertDontSee($taxRoute(), false);
    });
});

/*
|--------------------------------------------------------------------------
| Catalogue global-search gating (SearchController)
|--------------------------------------------------------------------------
|
| The product-group search block is gated on products.view; the product and
| stock-level blocks are gated on products.view / stock.view respectively.
| A user without those permissions has the gated blocks skipped entirely, so
| no query runs and the result sets stay empty.
|
| Note: the search blocks query with `ilike`, a PostgreSQL-only predicate
| (see test-db SQLite gotcha). On the SQLite test DB the *permitted* path
| would attempt the ilike query and error, so the executable assertions here
| cover the deny path (gate skips the block → no query) plus the unpermitted
| empty-result contract. The permitted-path query itself is exercised by the
| existing Product/StockLevel search coverage.
|
*/

describe('Catalogue global search gating', function () {
    it('skips the catalogue search blocks for a user without products/stock view', function () {
        $user = User::factory()->create();

        // 2+ chars so the controller does not short-circuit on query length;
        // the per-block gates then skip the product / stock / product-group
        // queries because the user lacks the permissions, leaving them empty.
        $this->actingAs($user)
            ->getJson(route('search').'?q='.urlencode('te'))
            ->assertOk()
            ->assertJson([
                'products' => [],
                'stock_levels' => [],
                'product_groups' => [],
            ]);
    });

    it('returns empty result sets for a short query regardless of permission', function () {
        $user = User::factory()->create();
        $user->givePermissionTo(['products.access', 'products.view', 'stock.access', 'stock.view']);

        // Sub-2-char query short-circuits before any gate or query, proving the
        // response contract without touching the pg-only ilike path on SQLite.
        $this->actingAs($user)
            ->getJson(route('search').'?q='.urlencode('a'))
            ->assertOk()
            ->assertJson([
                'products' => [],
                'stock_levels' => [],
                'product_groups' => [],
            ])
            ->assertJsonStructure(['members', 'products', 'stock_levels', 'product_groups', 'activities']);
    });
});
