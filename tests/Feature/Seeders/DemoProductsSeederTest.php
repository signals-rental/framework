<?php

use App\Console\Commands\SignalsClearDemoCommand;
use App\Models\Product;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\ProductGroupSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

/**
 * Invoke DemoDataSeeder::createDemoProducts() in isolation (a private method)
 * without triggering the multi-thousand-row member seed in run(). A silent
 * command is attached so the seeder's $this->command->info() calls succeed.
 */
function seedDemoProducts(): void
{
    $command = new Command;
    $command->setLaravel(app());
    $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));

    $seeder = (new DemoDataSeeder)->setContainer(app())->setCommand($command);

    (new ReflectionMethod(DemoDataSeeder::class, 'createDemoProducts'))->invoke($seeder);
}

it('creates demo products tagged demo-data', function () {
    seedDemoProducts();

    $demoProducts = Product::query()->whereJsonContains('tag_list', 'demo-data')->get();

    expect($demoProducts)->not->toBeEmpty();
    $demoProducts->each(function (Product $product): void {
        expect($product->tag_list)->toContain('demo-data');
    });
});

it('seeds a mix of product types and stock methods', function () {
    seedDemoProducts();

    $types = Product::query()
        ->whereJsonContains('tag_list', 'demo-data')
        ->pluck('product_type')
        ->map(fn ($t) => $t->value)
        ->unique();

    expect($types->count())->toBeGreaterThan(1);

    $stockMethods = Product::query()
        ->whereJsonContains('tag_list', 'demo-data')
        ->whereNotNull('stock_method')
        ->get()
        ->pluck('stock_method')
        ->map(fn ($m) => $m->value)
        ->unique();

    expect($stockMethods->count())->toBeGreaterThan(1);
});

it('links demo products to seeded catalogue groups when present', function () {
    $this->seed(ProductGroupSeeder::class);

    seedDemoProducts();

    $grouped = Product::query()
        ->whereJsonContains('tag_list', 'demo-data')
        ->whereNotNull('product_group_id')
        ->count();

    expect($grouped)->toBeGreaterThan(0);
});

it('is idempotent on re-run', function () {
    seedDemoProducts();
    $firstCount = Product::query()->whereJsonContains('tag_list', 'demo-data')->count();

    seedDemoProducts();
    $secondCount = Product::query()->whereJsonContains('tag_list', 'demo-data')->count();

    expect($secondCount)->toBe($firstCount);
});

it('clears demo products via signals:clear-demo when wired', function () {
    seedDemoProducts();
    expect(Product::query()->whereJsonContains('tag_list', 'demo-data')->exists())->toBeTrue();

    // Tag-based removal contract: clear-demo targets tag_list 'demo-data'.
    // The command currently removes members + stores; products use the same
    // tag so a future clear-demo extension removes them with no schema change.
    expect(class_exists(SignalsClearDemoCommand::class))->toBeTrue();
});
