<?php

use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the members index page', function () {
    $this->get('/members')
        ->assertOk()
        ->assertSee('Members');
});

it('lists members', function () {
    Member::factory()->contact()->create(['name' => 'John Smith']);
    Member::factory()->organisation()->create(['name' => 'Acme Corp']);

    Volt::test('members.index')
        ->assertSee('John Smith')
        ->assertSee('Acme Corp');
});

it('can search members by name', function () {
    if (config('database.default') === 'sqlite') {
        $this->markTestSkipped('Search uses PostgreSQL ilike operator');
    }

    Member::factory()->create(['name' => 'John Smith']);
    Member::factory()->create(['name' => 'Jane Doe']);

    Volt::test('members.index')
        ->set('search', 'John')
        ->assertSee('John Smith')
        ->assertDontSee('Jane Doe');
});

it('can filter by membership type', function () {
    Member::factory()->contact()->create(['name' => 'John Contact']);
    Member::factory()->organisation()->create(['name' => 'Acme Org']);

    Volt::test('members.index')
        ->set('typeFilter', MembershipType::Contact->value)
        ->assertSee('John Contact')
        ->assertDontSee('Acme Org');
});

it('can delete a member', function () {
    $member = Member::factory()->create(['name' => 'To Delete']);

    Volt::test('members.index')
        ->call('deleteMember', $member->id);

    expect(Member::withTrashed()->find($member->id)->trashed())->toBeTrue();
});

it('shows empty state when no members exist', function () {
    Volt::test('members.index')
        ->assertSee('No members found.');
});

it('links to member show page', function () {
    $member = Member::factory()->create(['name' => 'John Smith']);

    Volt::test('members.index')
        ->assertSeeHtml("/members/{$member->id}");
});

it('can bulk delete selected members', function () {
    $members = Member::factory()->count(3)->create();

    Volt::test('members.index')
        ->call('deleteSelected', $members->pluck('id')->all());

    foreach ($members as $member) {
        expect(Member::withTrashed()->find($member->id)->trashed())->toBeTrue();
    }
});

it('requires authentication', function () {
    auth()->logout();
    $this->get('/members')
        ->assertRedirect();
});

it('prevents non-owner from deleting a member', function () {
    $regularUser = User::factory()->create();
    $member = Member::factory()->create();

    $this->actingAs($regularUser);

    Volt::test('members.index')
        ->call('deleteMember', $member->id)
        ->assertForbidden();

    expect(Member::find($member->id))->not->toBeNull();
});

it('prevents non-owner from bulk deleting members', function () {
    $regularUser = User::factory()->create();
    $members = Member::factory()->count(2)->create();

    $this->actingAs($regularUser);

    Volt::test('members.index')
        ->call('deleteSelected', $members->pluck('id')->all())
        ->assertForbidden();

    foreach ($members as $member) {
        expect(Member::find($member->id))->not->toBeNull();
    }
});

it('ignores invalid membership type in setTypeFilter', function () {
    Volt::test('members.index')
        ->call('setTypeFilter', 'invalid_type')
        ->assertSet('typeFilter', '');
});
