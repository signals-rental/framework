<?php

use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\User;
use App\Views\MemberColumnRegistry;
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

it('can archive a member', function () {
    $member = Member::factory()->create(['name' => 'To Archive']);

    Volt::test('members.index')
        ->call('archiveMember', $member->id);

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

it('can bulk archive selected members', function () {
    $members = Member::factory()->count(3)->create();

    Volt::test('members.index')
        ->call('archiveSelected', $members->pluck('id')->all());

    foreach ($members as $member) {
        expect(Member::withTrashed()->find($member->id)->trashed())->toBeTrue();
    }
});

it('requires authentication', function () {
    auth()->logout();
    $this->get('/members')
        ->assertRedirect();
});

it('prevents non-owner from archiving a member', function () {
    $regularUser = User::factory()->create();
    $member = Member::factory()->create();

    $this->actingAs($regularUser);

    Volt::test('members.index')
        ->call('archiveMember', $member->id)
        ->assertForbidden();

    expect(Member::find($member->id))->not->toBeNull();
});

it('prevents non-owner from bulk archiving members', function () {
    $regularUser = User::factory()->create();
    $members = Member::factory()->count(2)->create();

    $this->actingAs($regularUser);

    Volt::test('members.index')
        ->call('archiveSelected', $members->pluck('id')->all())
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

it('derives shared table column flags from the MemberColumnRegistry', function () {
    Member::factory()->create(['name' => 'Registry Member']);

    $registry = app(MemberColumnRegistry::class);
    $columns = collect((array) Volt::test('members.index')->viewData('columns'))->keyBy('key');

    // The name/is_active/created_at columns map 1:1 to real members columns, so
    // their sortable/filterable flags must match the registry rather than a
    // divergent inline copy.
    foreach (['name', 'is_active', 'created_at'] as $key) {
        expect($columns[$key]['sortable'] ?? false)->toBe($registry->get($key)->sortable);
    }

    expect($columns['name']['filterable'] ?? false)->toBe($registry->get('name')->filterable);
    expect($columns['is_active']['filterable'] ?? false)->toBe($registry->get('is_active')->filterable);
});
