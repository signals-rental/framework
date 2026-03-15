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
    settings()->set('branding.logo_path', '/logos/acme.png');
    settings()->set('email.smtp_host', 'smtp.example.com');
    Store::create(['name' => 'HQ', 'is_default' => true]);
    $owner = User::factory()->create(['is_owner' => true]);
    User::factory()->create(); // second user = team member invited

    Livewire::actingAs($owner)
        ->test(GettingStartedChecklist::class)
        ->assertSee('75% complete');
});

it('has 8 checklist items', function () {
    $user = User::factory()->create();

    $component = new GettingStartedChecklist;
    $component->mount();

    expect($component->items())->toHaveCount(8);
});

it('shows progress based on completed items', function () {
    settings()->set('company.name', 'Acme Rentals');
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(GettingStartedChecklist::class);

    $component->assertDontSee('100% complete');
});
