<?php

use App\Livewire\Dashboard\RecentOpportunities;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Dashboard recent-opportunities widget (A4a)
|--------------------------------------------------------------------------
|
| The widget lists the latest opportunities (number, subject, member, status,
| charge total) with each row linking to the Show page and a "View All" link to
| the Index. The whole widget is gated on `opportunities.access` — a user
| without it sees nothing. Factory projection rows are sufficient (the widget
| only reads the projection, no live Verbs state needed).
|
*/

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

it('lists the latest opportunities for a permitted user', function () {
    $member = Member::factory()->organisation()->create(['name' => 'Greenfield Events Ltd']);

    $opportunity = Opportunity::factory()->order()->create([
        'member_id' => $member->id,
        'number' => 'OPP-100',
        'subject' => 'Summer Festival Stage',
        'charge_total' => 1245050,
        'currency_code' => 'GBP',
    ]);

    Livewire::actingAs($this->owner)
        ->test(RecentOpportunities::class)
        ->assertOk()
        ->assertSee('Recent Opportunities')
        ->assertSee('OPP-100')
        ->assertSee('Summer Festival Stage')
        ->assertSee('Greenfield Events Ltd')
        ->assertSee('12450.50')
        ->assertSeeHtml(route('opportunities.show', $opportunity))
        ->assertSeeHtml(route('opportunities.index'));
});

it('limits the list to the five most recent opportunities, newest first', function () {
    $opportunities = collect(range(1, 7))->map(
        fn (int $n): Opportunity => Opportunity::factory()->order()->create([
            'subject' => "Opportunity number {$n}",
        ])
    );

    $component = Livewire::actingAs($this->owner)->test(RecentOpportunities::class);

    $listed = $component->viewData('opportunities');

    expect($listed)->toHaveCount(5)
        // latest() orders by created_at desc; the last-created row leads.
        ->and($listed->first()->id)->toBe($opportunities->last()->id)
        ->and($listed->pluck('id'))->not->toContain($opportunities->first()->id);
});

it('renders an empty state when there are no opportunities', function () {
    Livewire::actingAs($this->owner)
        ->test(RecentOpportunities::class)
        ->assertOk()
        ->assertSee('Recent Opportunities')
        ->assertSee('No opportunities yet.');
});

it('renders nothing for a user without opportunities.access', function () {
    Opportunity::factory()->order()->create(['subject' => 'Hidden opportunity']);

    $viewer = User::factory()->create();

    Livewire::actingAs($viewer)
        ->test(RecentOpportunities::class)
        ->assertOk()
        ->assertDontSee('Recent Opportunities')
        ->assertDontSee('Hidden opportunity');
});
