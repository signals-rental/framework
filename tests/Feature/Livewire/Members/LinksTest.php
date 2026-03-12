<?php

use App\Models\Link;
use App\Models\Member;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
    $this->member = Member::factory()->create(['name' => 'Test Member']);
});

it('renders the links page', function () {
    $this->get("/members/{$this->member->id}/links")
        ->assertOk()
        ->assertSee('Links');
});

it('lists links for a member', function () {
    Link::factory()->create([
        'linkable_type' => Member::class,
        'linkable_id' => $this->member->id,
        'url' => 'https://example.com',
        'name' => 'Example Site',
    ]);
    Link::factory()->create([
        'linkable_type' => Member::class,
        'linkable_id' => $this->member->id,
        'url' => 'https://other.com',
        'name' => 'Other Site',
    ]);

    Volt::test('members.links', ['member' => $this->member])
        ->assertSee('https://example.com')
        ->assertSee('https://other.com');
});

it('shows empty state when no links exist', function () {
    Volt::test('members.links', ['member' => $this->member])
        ->assertSee('No links found.');
});

it('can delete a link', function () {
    $link = Link::factory()->create([
        'linkable_type' => Member::class,
        'linkable_id' => $this->member->id,
    ]);

    Volt::test('members.links', ['member' => $this->member])
        ->call('deleteLink', $link->id);

    expect(Link::find($link->id))->toBeNull();
});

it('renders the create link form', function () {
    $this->get("/members/{$this->member->id}/links/create")
        ->assertOk()
        ->assertSee('Add Link');
});

it('can create a link', function () {
    Volt::test('members.link-form', ['member' => $this->member])
        ->set('url', 'https://newsite.com')
        ->set('name', 'New Site')
        ->call('save')
        ->assertRedirect();

    expect($this->member->links()->where('url', 'https://newsite.com')->exists())->toBeTrue();
});

it('can edit a link', function () {
    $link = Link::factory()->create([
        'linkable_type' => Member::class,
        'linkable_id' => $this->member->id,
        'url' => 'https://old.com',
        'name' => 'Old Site',
    ]);

    Volt::test('members.link-form', ['member' => $this->member, 'link' => $link])
        ->assertSet('url', 'https://old.com')
        ->assertSet('name', 'Old Site')
        ->set('url', 'https://new.com')
        ->set('name', 'New Site')
        ->call('save')
        ->assertRedirect();

    expect($link->fresh()->url)->toBe('https://new.com');
    expect($link->fresh()->name)->toBe('New Site');
});

it('validates required url', function () {
    Volt::test('members.link-form', ['member' => $this->member])
        ->set('url', '')
        ->call('save')
        ->assertHasErrors(['url']);
});

it('validates url format', function () {
    Volt::test('members.link-form', ['member' => $this->member])
        ->set('url', 'not-a-url')
        ->call('save')
        ->assertHasErrors(['url']);
});

it('cannot delete another member\'s link', function () {
    $otherMember = Member::factory()->create();
    $otherLink = Link::factory()->create([
        'linkable_type' => Member::class,
        'linkable_id' => $otherMember->id,
    ]);

    expect(fn () => Volt::test('members.links', ['member' => $this->member])
        ->call('deleteLink', $otherLink->id)
    )->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    expect(Link::find($otherLink->id))->not->toBeNull();
});

it('requires authentication', function () {
    auth()->logout();
    $this->get("/members/{$this->member->id}/links")
        ->assertRedirect();
});
