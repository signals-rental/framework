<?php

use App\Models\Member;
use App\Models\Phone;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
    $this->member = Member::factory()->create(['name' => 'Test Member']);
});

it('renders the phones page', function () {
    $this->get("/members/{$this->member->id}/phones")
        ->assertOk()
        ->assertSee('Phones');
});

it('lists phones for a member', function () {
    Phone::factory()->create([
        'phoneable_type' => Member::class,
        'phoneable_id' => $this->member->id,
        'number' => '020 7946 0958',
    ]);
    Phone::factory()->create([
        'phoneable_type' => Member::class,
        'phoneable_id' => $this->member->id,
        'number' => '0161 496 0000',
    ]);

    Volt::test('members.phones', ['member' => $this->member])
        ->assertSee('020 7946 0958')
        ->assertSee('0161 496 0000');
});

it('shows empty state when no phones exist', function () {
    Volt::test('members.phones', ['member' => $this->member])
        ->assertSee('No phone numbers found.');
});

it('shows primary badge on primary phone', function () {
    Phone::factory()->primary()->create([
        'phoneable_type' => Member::class,
        'phoneable_id' => $this->member->id,
    ]);

    Volt::test('members.phones', ['member' => $this->member])
        ->assertSee('Primary');
});

it('can delete a phone', function () {
    $phone = Phone::factory()->create([
        'phoneable_type' => Member::class,
        'phoneable_id' => $this->member->id,
    ]);

    Volt::test('members.phones', ['member' => $this->member])
        ->call('deletePhone', $phone->id);

    expect(Phone::find($phone->id))->toBeNull();
});

it('renders the create phone form', function () {
    $this->get("/members/{$this->member->id}/phones/create")
        ->assertOk()
        ->assertSee('Add Phone');
});

it('can create a phone', function () {
    Volt::test('members.phone-form', ['member' => $this->member])
        ->set('number', '020 7946 1234')
        ->set('isPrimary', false)
        ->call('save')
        ->assertRedirect();

    expect($this->member->phones()->where('number', '020 7946 1234')->exists())->toBeTrue();
});

it('can edit a phone', function () {
    $phone = Phone::factory()->create([
        'phoneable_type' => Member::class,
        'phoneable_id' => $this->member->id,
        'number' => '020 7946 0000',
    ]);

    Volt::test('members.phone-form', ['member' => $this->member, 'phone' => $phone])
        ->assertSet('number', '020 7946 0000')
        ->set('number', '020 7946 9999')
        ->call('save')
        ->assertRedirect();

    expect($phone->fresh()->number)->toBe('020 7946 9999');
});

it('validates required phone number', function () {
    Volt::test('members.phone-form', ['member' => $this->member])
        ->set('number', '')
        ->call('save')
        ->assertHasErrors(['number']);
});

it('can set primary phone', function () {
    $existing = Phone::factory()->primary()->create([
        'phoneable_type' => Member::class,
        'phoneable_id' => $this->member->id,
    ]);

    Volt::test('members.phone-form', ['member' => $this->member])
        ->set('number', '020 7946 5555')
        ->set('isPrimary', true)
        ->call('save')
        ->assertRedirect();

    expect($existing->fresh()->is_primary)->toBeFalse();
    expect($this->member->phones()->where('number', '020 7946 5555')->first()->is_primary)->toBeTrue();
});

it('cannot delete another member\'s phone', function () {
    $otherMember = Member::factory()->create();
    $otherPhone = Phone::factory()->create([
        'phoneable_type' => Member::class,
        'phoneable_id' => $otherMember->id,
    ]);

    expect(fn () => Volt::test('members.phones', ['member' => $this->member])
        ->call('deletePhone', $otherPhone->id)
    )->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    expect(Phone::find($otherPhone->id))->not->toBeNull();
});

it('requires authentication', function () {
    auth()->logout();
    $this->get("/members/{$this->member->id}/phones")
        ->assertRedirect();
});
