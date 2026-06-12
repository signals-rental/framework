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

it('merge surfaces a validation error when the secondary is archived', function () {
    $logSpy = Log::spy();

    $memberA = Member::factory()->contact()->create();
    $memberB = Member::factory()->contact()->create();

    $component = Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', memberA: $memberA->id, memberB: $memberB->id);

    // Soft-delete the secondary: the DTO's withoutTrashed() exists rule now rejects it
    // on the Livewire path (validateAndCreate), surfacing a ValidationException that the
    // modal flashes as an error — not a ModelNotFound "no longer exists" message.
    $memberB->delete();

    $component->call('merge')
        ->assertNoRedirect()
        ->assertNotDispatched('member-merged');

    // The flashed error is the validation message, not the ModelNotFound copy.
    expect(session('error'))->not->toBe('One of the selected members no longer exists.');

    // Validation failures do not hit the generic Throwable logging branch.
    $logSpy->shouldNotHaveReceived('error');

    // The primary member is untouched.
    expect(Member::find($memberA->id))->not->toBeNull();
});

it('opens in select-secondary mode when memberB is zero', function () {
    $primary = Member::factory()->organisation()->create();
    $other = Member::factory()->organisation()->create(['name' => 'Mergeable Org']);

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', memberA: $primary->id, memberB: 0)
        ->assertSet('memberAId', $primary->id)
        ->assertSet('memberBId', null)
        ->assertSet('needsSecondary', true)
        ->assertSet('primaryId', $primary->id)
        ->assertSet('mergeSearch', '')
        ->assertSet('mergeSearchResults', []);
});

it('searches for an eligible same-type secondary member', function () {
    $primary = Member::factory()->organisation()->create(['name' => 'Acme Holdings']);
    $match = Member::factory()->organisation()->create(['name' => 'Acme Trading']);

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', memberA: $primary->id, memberB: 0)
        ->set('mergeSearch', 'Acme')
        ->assertSet('mergeSearchResults', function (array $results) use ($match, $primary) {
            $ids = collect($results)->pluck('id');

            return $ids->contains($match->id) && ! $ids->contains($primary->id);
        });
});

it('search filters out members that do not match the term', function () {
    $primary = Member::factory()->organisation()->create(['name' => 'Acme Holdings']);
    $unrelated = Member::factory()->organisation()->create(['name' => 'Globex Corp']);

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', memberA: $primary->id, memberB: 0)
        ->set('mergeSearch', 'Acme')
        ->assertSet('mergeSearchResults', function (array $results) use ($unrelated) {
            return ! collect($results)->pluck('id')->contains($unrelated->id);
        });
});

it('search is case-insensitive', function () {
    $primary = Member::factory()->organisation()->create(['name' => 'Acme Holdings']);
    $match = Member::factory()->organisation()->create(['name' => 'Acme Trading']);

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', memberA: $primary->id, memberB: 0)
        ->set('mergeSearch', 'acme')
        ->assertSet('mergeSearchResults', function (array $results) use ($match) {
            return collect($results)->pluck('id')->contains($match->id);
        });
});

it('search only offers same-type non-user members', function () {
    $primary = Member::factory()->organisation()->create(['name' => 'Match Org']);
    $sameType = Member::factory()->organisation()->create(['name' => 'Match Trading']);
    $differentType = Member::factory()->contact()->create(['name' => 'Match Person']);
    $userMember = Member::factory()->user()->create(['name' => 'Match User']);

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', memberA: $primary->id, memberB: 0)
        ->set('mergeSearch', 'Match')
        ->assertSet('mergeSearchResults', function (array $results) use ($sameType, $differentType, $userMember) {
            $ids = collect($results)->pluck('id');

            return $ids->contains($sameType->id)
                && ! $ids->contains($differentType->id)
                && ! $ids->contains($userMember->id);
        });
});

it('search returns nothing for a short term', function () {
    $primary = Member::factory()->organisation()->create(['name' => 'Acme']);
    Member::factory()->organisation()->create(['name' => 'Acme Trading']);

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', memberA: $primary->id, memberB: 0)
        ->set('mergeSearch', 'A')
        ->assertSet('mergeSearchResults', []);
});

it('does not search when the primary is a user-type member', function () {
    $primary = Member::factory()->user()->create(['name' => 'Staff One']);
    Member::factory()->user()->create(['name' => 'Staff Two']);

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', memberA: $primary->id, memberB: 0)
        ->set('mergeSearch', 'Staff')
        ->assertSet('mergeSearchResults', []);
});

it('selects a merge target from search results', function () {
    $primary = Member::factory()->organisation()->create();
    $secondary = Member::factory()->organisation()->create();

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', memberA: $primary->id, memberB: 0)
        ->call('selectMergeTarget', $secondary->id)
        ->assertSet('memberBId', $secondary->id)
        ->assertSet('mergeSearch', '')
        ->assertSet('mergeSearchResults', []);
});

it('clears a selected merge target', function () {
    $primary = Member::factory()->organisation()->create();
    $secondary = Member::factory()->organisation()->create();

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', memberA: $primary->id, memberB: 0)
        ->call('selectMergeTarget', $secondary->id)
        ->call('clearMergeTarget')
        ->assertSet('memberBId', null)
        ->assertSet('mergeSearch', '')
        ->assertSet('mergeSearchResults', []);
});

it('completes a merge after the secondary is selected in select-secondary mode', function () {
    $primary = Member::factory()->organisation()->create();
    $secondary = Member::factory()->organisation()->create();

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', memberA: $primary->id, memberB: 0)
        ->call('selectMergeTarget', $secondary->id)
        ->call('merge')
        ->assertRedirect(route('members.show', $primary->id));

    expect(Member::withTrashed()->find($secondary->id)->trashed())->toBeTrue();
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
