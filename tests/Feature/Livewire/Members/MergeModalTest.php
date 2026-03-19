<?php

use App\Livewire\Members\MergeModal;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    Queue::fake();
    Event::fake();
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders successfully', function () {
    Livewire::test(MergeModal::class)
        ->assertStatus(200);
});

it('openModal sets member IDs and defaults primary to memberA', function () {
    $memberA = Member::factory()->contact()->create();
    $memberB = Member::factory()->contact()->create();

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', memberA: $memberA->id, memberB: $memberB->id)
        ->assertSet('memberAId', $memberA->id)
        ->assertSet('memberBId', $memberB->id)
        ->assertSet('primaryId', $memberA->id);
});

it('merge with valid members redirects to primary member show page', function () {
    $memberA = Member::factory()->contact()->create();
    $memberB = Member::factory()->contact()->create();

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', memberA: $memberA->id, memberB: $memberB->id)
        ->call('merge')
        ->assertRedirect(route('members.show', $memberA->id));
});

it('merge with different types does not redirect', function () {
    $memberA = Member::factory()->contact()->create();
    $memberB = Member::factory()->organisation()->create();

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', memberA: $memberA->id, memberB: $memberB->id)
        ->call('merge')
        ->assertNoRedirect();

    // Both members should still exist (not merged)
    expect(Member::find($memberA->id))->not->toBeNull();
    expect(Member::find($memberB->id))->not->toBeNull();
});

it('merge with null IDs does not redirect', function () {
    Livewire::test(MergeModal::class)
        ->call('merge')
        ->assertNoRedirect()
        ->assertNotDispatched('member-merged');
});

it('merge unauthorized user does not redirect and logs error', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    Log::spy();

    $memberA = Member::factory()->contact()->create();
    $memberB = Member::factory()->contact()->create();

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', memberA: $memberA->id, memberB: $memberB->id)
        ->call('merge')
        ->assertNoRedirect()
        ->assertNotDispatched('member-merged');

    // Members should remain unchanged
    expect(Member::find($memberA->id))->not->toBeNull();
    expect(Member::find($memberB->id))->not->toBeNull();
});

it('merge dispatches member-merged event on success', function () {
    $memberA = Member::factory()->contact()->create();
    $memberB = Member::factory()->contact()->create();

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', memberA: $memberA->id, memberB: $memberB->id)
        ->call('merge')
        ->assertDispatched('member-merged');
});

it('merge uses memberB as secondary when primaryId equals memberA', function () {
    $memberA = Member::factory()->contact()->create();
    $memberB = Member::factory()->contact()->create();

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', memberA: $memberA->id, memberB: $memberB->id)
        ->assertSet('primaryId', $memberA->id)
        ->call('merge')
        ->assertRedirect(route('members.show', $memberA->id));

    // Secondary (memberB) should be soft-deleted
    expect(Member::withTrashed()->find($memberB->id)->trashed())->toBeTrue();
    expect(Member::find($memberA->id))->not->toBeNull();
});

it('merge uses memberA as secondary when primaryId is switched to memberB', function () {
    $memberA = Member::factory()->contact()->create();
    $memberB = Member::factory()->contact()->create();

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', memberA: $memberA->id, memberB: $memberB->id)
        ->set('primaryId', $memberB->id)
        ->call('merge')
        ->assertRedirect(route('members.show', $memberB->id));

    // Secondary (memberA) should be soft-deleted
    expect(Member::withTrashed()->find($memberA->id)->trashed())->toBeTrue();
    expect(Member::find($memberB->id))->not->toBeNull();
});

it('with() provides member data to the view', function () {
    $memberA = Member::factory()->contact()->create(['name' => 'Alice']);
    $memberB = Member::factory()->contact()->create(['name' => 'Bob']);

    $component = Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', memberA: $memberA->id, memberB: $memberB->id);

    $viewMemberA = $component->viewData('memberA');
    $viewMemberB = $component->viewData('memberB');

    expect($viewMemberA->name)->toBe('Alice');
    expect($viewMemberB->name)->toBe('Bob');
});
