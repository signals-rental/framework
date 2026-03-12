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

it('renders the create form', function () {
    $this->get('/members/create')
        ->assertOk()
        ->assertSee('Create Member');
});

it('renders the edit form with populated data', function () {
    $member = Member::factory()->contact()->create(['name' => 'John Smith']);

    Volt::test('members.form', ['member' => $member])
        ->assertSet('name', 'John Smith')
        ->assertSet('membershipType', MembershipType::Contact->value)
        ->assertSet('isActive', true);
});

it('can create a member', function () {
    Volt::test('members.form')
        ->set('name', 'New Member')
        ->set('membershipType', MembershipType::Contact->value)
        ->set('isActive', true)
        ->call('save')
        ->assertRedirect();

    expect(Member::where('name', 'New Member')->exists())->toBeTrue();
});

it('can edit a member', function () {
    $member = Member::factory()->create(['name' => 'Old Name']);

    Volt::test('members.form', ['member' => $member])
        ->set('name', 'New Name')
        ->call('save')
        ->assertRedirect();

    expect($member->fresh()->name)->toBe('New Name');
});

it('validates required name', function () {
    Volt::test('members.form')
        ->set('name', '')
        ->set('membershipType', MembershipType::Contact->value)
        ->call('save')
        ->assertHasErrors(['name']);
});

it('validates required membership type', function () {
    Volt::test('members.form')
        ->set('name', 'Test Member')
        ->set('membershipType', '')
        ->call('save')
        ->assertHasErrors(['membershipType']);
});

it('validates invalid membership type', function () {
    Volt::test('members.form')
        ->set('name', 'Test Member')
        ->set('membershipType', 'invalid_type')
        ->call('save')
        ->assertHasErrors(['membershipType']);
});

it('can create an inactive member', function () {
    Volt::test('members.form')
        ->set('name', 'Inactive Member')
        ->set('membershipType', MembershipType::Organisation->value)
        ->set('isActive', false)
        ->call('save')
        ->assertRedirect();

    $member = Member::where('name', 'Inactive Member')->first();
    expect($member->is_active)->toBeFalse();
});

it('requires authentication', function () {
    auth()->logout();
    $this->get('/members/create')
        ->assertRedirect();
});

it('prevents non-owner from creating a member', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    Volt::test('members.form')
        ->set('name', 'Unauthorized Member')
        ->set('membershipType', MembershipType::Contact->value)
        ->call('save')
        ->assertForbidden();

    expect(Member::where('name', 'Unauthorized Member')->exists())->toBeFalse();
});

it('prevents non-owner from editing a member', function () {
    $regularUser = User::factory()->create();
    $member = Member::factory()->create(['name' => 'Original Name']);
    $this->actingAs($regularUser);

    Volt::test('members.form', ['member' => $member])
        ->set('name', 'Changed Name')
        ->call('save')
        ->assertForbidden();

    expect($member->fresh()->name)->toBe('Original Name');
});
