<?php

use App\Models\Product;
use App\Services\Opportunities\ProductSearchService;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL pg_trgm product-search lane
|--------------------------------------------------------------------------
|
| Proves the server tier of the opportunity line-item product search ranks via
| real `pg_trgm` trigram similarity on PostgreSQL (the `%` operator + the GIN
| trigram index added by add_product_search_trgm_index). This is the path the
| SQLite suite cannot exercise — SQLite degrades to an ilike rank. Runs against
| the dedicated pgsql_testing connection; SKIPs when Postgres is unreachable.
|
|   php artisan test --compact --group=pgsql
|
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function (): void {
    $this->service = app(ProductSearchService::class);
});

it('ranks products by trigram similarity and tolerates typos', function (): void {
    Product::factory()->create(['name' => 'Spiider Light', 'sku' => 'SPD-001']);
    Product::factory()->create(['name' => 'Spider Web Truss', 'sku' => 'SPW-002']);
    Product::factory()->create(['name' => 'Unrelated Cable', 'sku' => 'CAB-009']);

    // Misspelt query — pg_trgm should still surface the Spi(i)der products,
    // which an exact ilike substring search would miss.
    $results = $this->service->search('spidder');

    expect($results->pluck('name'))->toContain('Spiider Light')
        ->and($results->pluck('name'))->not->toContain('Unrelated Cable');
});

it('matches on sku via the trigram index', function (): void {
    Product::factory()->create(['name' => 'Moving Head', 'sku' => 'ROBE-MEGAPOINTE']);
    Product::factory()->create(['name' => 'Par Can', 'sku' => 'PAR-64']);

    $results = $this->service->search('megapointe');

    expect($results->pluck('name'))->toContain('Moving Head')
        ->and($results->pluck('name'))->not->toContain('Par Can');
});

it('orders the closest trigram match first', function (): void {
    Product::factory()->create(['name' => 'Spiider', 'sku' => 'A']);
    Product::factory()->create(['name' => 'Spiider Mega Floor Package', 'sku' => 'B']);

    $results = $this->service->search('spiider');

    // The shorter, near-exact name is the closer trigram match.
    expect($results->first()->name)->toBe('Spiider');
});
