<?php

use App\Models\Member;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->member = Member::factory()->create(['name' => 'Test Member']);
});

it('shows the what3words lookup when the integration is configured', function () {
    settings()->set('integrations.what3words_api_key', 'test-api-key');
    $this->actingAs(User::factory()->owner()->create());

    Volt::test('members.address-form', ['member' => $this->member])
        ->assertSee('e.g. filled.count.soap')
        ->assertSee('Lookup')
        ->assertDontSee('what3words is not configured');
});

it('hides the what3words lookup and shows a configure note when not configured', function () {
    settings()->set('integrations.what3words_api_key', '');
    $this->actingAs(User::factory()->owner()->create());

    Volt::test('members.address-form', ['member' => $this->member])
        ->assertDontSee('e.g. filled.count.soap')
        ->assertSee('what3words is not configured')
        ->assertSee('Set it up in Integrations settings.');
});

it('shows the admin settings link only to users who can manage integrations', function () {
    settings()->set('integrations.what3words_api_key', '');
    $this->actingAs(User::factory()->owner()->create());

    Volt::test('members.address-form', ['member' => $this->member])
        ->assertSee(route('admin.settings.integrations'));
});

it('hides the admin settings link from users without admin access', function () {
    settings()->set('integrations.what3words_api_key', '');
    $this->actingAs(User::factory()->create());

    Volt::test('members.address-form', ['member' => $this->member])
        ->assertSee('what3words is not configured')
        ->assertDontSee(route('admin.settings.integrations'));
});

it('lookupWhat3words reports a configure error when not configured', function () {
    settings()->set('integrations.what3words_api_key', '');
    $this->actingAs(User::factory()->owner()->create());

    Volt::test('members.address-form', ['member' => $this->member])
        ->set('what3words', 'filled.count.soap')
        ->call('lookupWhat3words')
        ->assertHasErrors('what3words');
});

it('still offers Nominatim geocoding regardless of what3words configuration', function () {
    settings()->set('integrations.what3words_api_key', '');
    $this->actingAs(User::factory()->create());

    Volt::test('members.address-form', ['member' => $this->member])
        ->assertSee('Geocode from Address');
});
