<?php

use App\Livewire\Dashboard\GettingStartedChecklist;
use App\Models\Store;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

it('renders when not dismissed', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(GettingStartedChecklist::class)
        ->assertSee('Getting Started')
        ->assertSee('Complete these steps');
});

it('does not render when dismissed', function () {
    settings()->set('dashboard.checklist_dismissed', true, 'boolean');

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(GettingStartedChecklist::class)
        ->assertDontSee('Getting Started');
});

it('can be dismissed', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(GettingStartedChecklist::class)
        ->assertSee('Getting Started')
        ->call('dismiss')
        ->assertDontSee('Getting Started');

    expect(settings('dashboard.checklist_dismissed'))->toBeTrue();
});

it('shows completed items when setup is done', function () {
    settings()->set('company.name', 'Acme Rentals');
    Store::create(['name' => 'HQ', 'is_default' => true]);
    $user = User::factory()->create(['is_owner' => true]);

    Livewire::actingAs($user)
        ->test(GettingStartedChecklist::class)
        ->assertSee('100% complete');
});

it('shows progress based on completed items', function () {
    settings()->set('company.name', 'Acme Rentals');
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(GettingStartedChecklist::class);

    $component->assertDontSee('100% complete');
});
