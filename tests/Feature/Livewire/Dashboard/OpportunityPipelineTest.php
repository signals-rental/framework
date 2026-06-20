<?php

use App\Livewire\Dashboard\OpportunityPipeline;
use App\Models\Opportunity;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Dashboard opportunity-pipeline widget (M8-6)
|--------------------------------------------------------------------------
|
| The widget renders four cheap aggregate stats (open quotations, live orders,
| orders due to dispatch soon, opportunities with shortages), each linking to a
| filtered Index. The whole widget is gated on `opportunities.access` — a user
| without it sees nothing. Factory projection rows are sufficient (the widget
| only reads counts, no live Verbs state needed).
|
*/

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

it('renders the pipeline counts for a permitted user', function () {
    Opportunity::factory()->count(3)->quotation()->create();
    Opportunity::factory()->count(2)->order()->create();
    Opportunity::factory()->order()->withShortage()->create();

    Livewire::actingAs($this->owner)
        ->test(OpportunityPipeline::class)
        ->assertOk()
        ->assertSee('Opportunity Pipeline')
        ->assertSee('Open Quotations')
        ->assertSee('Live Orders')
        ->assertSee('Due to Dispatch')
        ->assertSee('With Shortages');
});

it('counts quotations, orders and shortages from the projection', function () {
    Opportunity::factory()->count(3)->quotation()->create();
    Opportunity::factory()->count(2)->order()->create();
    Opportunity::factory()->order()->withShortage()->create();

    $component = Livewire::actingAs($this->owner)
        ->test(OpportunityPipeline::class);

    $counts = $component->viewData('counts');

    expect($counts['quotations'])->toBe(3)
        ->and($counts['orders'])->toBe(3) // 2 plain orders + 1 shortage order
        ->and($counts['shortages'])->toBe(1);
});

it('counts only orders starting within the due-soon window', function () {
    // Order starting in 3 days — counted.
    Opportunity::factory()->order()->create(['starts_at' => Carbon::now()->addDays(3)]);
    // Order starting in 30 days — outside the window.
    Opportunity::factory()->order()->create(['starts_at' => Carbon::now()->addDays(30)]);
    // Order with no start date — not counted.
    Opportunity::factory()->order()->create(['starts_at' => null]);
    // Quotation starting soon — wrong state, not counted as an order due.
    Opportunity::factory()->quotation()->create(['starts_at' => Carbon::now()->addDays(2)]);

    $component = Livewire::actingAs($this->owner)
        ->test(OpportunityPipeline::class);

    expect($component->viewData('counts')['due_soon'])->toBe(1);
});

it('renders nothing for a user without opportunities.access', function () {
    Opportunity::factory()->count(3)->quotation()->create();

    $viewer = User::factory()->create();

    Livewire::actingAs($viewer)
        ->test(OpportunityPipeline::class)
        ->assertOk()
        ->assertDontSee('Opportunity Pipeline')
        ->assertDontSee('Open Quotations');
});
