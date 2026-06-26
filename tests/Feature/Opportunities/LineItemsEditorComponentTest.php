<?php

use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\CreateOpportunityData;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

it('exposes tree and revision for the frozen seed island', function () {
    Auth::login($this->owner);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Seed contract',
        'store_id' => $this->store->id,
    ]));

    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    $this->actingAs($this->owner);

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSeeHtml('data-lf-seed')
        ->assertSeeHtml('window.__lfSeed')
        ->assertSeeHtml('window.__lfEditorConfig')
        ->assertSeeHtml('wire:ignore')
        ->assertSeeHtml('x-data="window.signals.lineItemsEditor({ oppId: '.$opportunity->id.' })"')
        ->assertSeeHtml('window.__lfEditorConfig')
        ->assertSee('Quick add');

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity]);
    $instance = lineItemsEditorInstance($component);

    expect($instance->treeRevision())->toBeInt();

    $server = $instance->serverTree();

    expect($server)->toHaveKeys(['tree', 'revision']);
});

it('keeps a stable x-data attribute after inline add so Alpine is not re-initialised', function () {
    Auth::login($this->owner);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Stable x-data',
        'store_id' => $this->store->id,
    ]));

    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    $this->actingAs($this->owner);

    $stableXData = 'x-data="window.signals.lineItemsEditor({ oppId: '.$opportunity->id.' })"';

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->assertSeeHtml($stableXData)
        ->call('addInlineTextLine')
        ->assertHasNoErrors()
        ->assertSeeHtml($stableXData);
});

it('renders read-only for view-only users', function () {
    Auth::login($this->owner);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Read only',
        'store_id' => $this->store->id,
    ]));

    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('opportunities.access', 'opportunities.view');
    $this->actingAs($viewer);

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSee('This opportunity has no line items yet.')
        ->assertDontSee('Quick add');
});
