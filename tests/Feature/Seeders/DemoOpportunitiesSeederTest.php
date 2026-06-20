<?php

use App\Enums\OpportunityState;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);

    // The async availability recalculation jobs are queued by the demand
    // observers; fake the queue so the seeder runs without a worker.
    Queue::fake();

    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    // An owner is required so the event-sourced action Gate checks pass.
    User::factory()->owner()->create();

    // The seeder needs a default store, demo organisations, and demo products.
    Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);

    Member::factory()
        ->organisation()
        ->count(3)
        ->create(['tag_list' => ['demo-data']]);

    Product::factory()
        ->rental()
        ->count(3)
        ->create(['tag_list' => ['demo-data']]);
});

/**
 * Invoke DemoDataSeeder::createDemoOpportunities() in isolation (a private
 * method) without triggering the multi-thousand-row member seed in run(). A
 * silent command is attached so the seeder's $this->command->info() calls
 * succeed.
 */
function seedDemoOpportunities(): void
{
    $command = new Command;
    $command->setLaravel(app());
    $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));

    $seeder = (new DemoDataSeeder)->setContainer(app())->setCommand($command);

    (new ReflectionMethod(DemoDataSeeder::class, 'createDemoOpportunities'))->invoke($seeder);
}

it('seeds demo opportunities tagged demo-data via the event-sourced path', function () {
    seedDemoOpportunities();

    $demoOpportunities = Opportunity::query()
        ->whereJsonContains('tag_list', 'demo-data')
        ->get();

    expect($demoOpportunities)->not->toBeEmpty();
    $demoOpportunities->each(function (Opportunity $opportunity): void {
        expect($opportunity->tag_list)->toContain('demo-data')
            ->and($opportunity->number)->not->toBeNull();
    });
});

it('seeds a spread across draft, quotation and order states', function () {
    seedDemoOpportunities();

    $states = Opportunity::query()
        ->whereJsonContains('tag_list', 'demo-data')
        ->get()
        ->pluck('state')
        ->map(fn (OpportunityState $state): int => $state->value)
        ->unique();

    expect($states)->toContain(OpportunityState::Order->value)
        ->and($states)->toContain(OpportunityState::Quotation->value)
        ->and($states)->toContain(OpportunityState::Draft->value);
});

it('gives every demo opportunity at least one line item', function () {
    seedDemoOpportunities();

    Opportunity::query()
        ->whereJsonContains('tag_list', 'demo-data')
        ->get()
        ->each(function (Opportunity $opportunity): void {
            expect($opportunity->items()->count())->toBeGreaterThan(0);
        });
});

it('is idempotent on re-run', function () {
    seedDemoOpportunities();
    $firstCount = Opportunity::query()->whereJsonContains('tag_list', 'demo-data')->count();

    seedDemoOpportunities();
    $secondCount = Opportunity::query()->whereJsonContains('tag_list', 'demo-data')->count();

    expect($secondCount)->toBe($firstCount);
});

it('returns early without erroring when there are no demo members or products', function () {
    Member::query()->whereJsonContains('tag_list', 'demo-data')->forceDelete();
    Product::query()->whereJsonContains('tag_list', 'demo-data')->forceDelete();

    seedDemoOpportunities();

    expect(Opportunity::query()->whereJsonContains('tag_list', 'demo-data')->count())->toBe(0);
});
