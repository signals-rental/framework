<?php

namespace App\Livewire\Members;

use App\Actions\Members\MergeMember;
use App\Data\Members\MergeMemberData;
use App\Enums\MembershipType;
use App\Livewire\Concerns\HandlesMergeErrors;
use App\Models\Member;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class MergeModal extends Component
{
    use HandlesMergeErrors;

    public ?int $memberAId = null;

    public ?int $memberBId = null;

    public ?int $primaryId = null;

    /**
     * When opened from a member's page with only a primary member, the modal
     * shows a picker for the second member rather than a two-member comparison.
     */
    public bool $needsSecondary = false;

    public string $mergeSearch = '';

    /** @var list<array{id: int, name: string}> */
    public array $mergeSearchResults = [];

    #[On('open-merge-modal')]
    public function openModal(int $memberA, int $memberB): void
    {
        $this->memberAId = $memberA;
        // A memberB of 0 means "no second member chosen yet" — the modal opens
        // in select-secondary mode with this member pre-set as the primary.
        $this->memberBId = $memberB > 0 ? $memberB : null;
        $this->needsSecondary = $this->memberBId === null;
        $this->primaryId = $memberA;
        $this->mergeSearch = '';
        $this->mergeSearchResults = [];
        $this->js("setTimeout(() => \$dispatch('open-modal', 'merge-members'), 50)");
    }

    public function updatedMemberBId(mixed $value): void
    {
        $this->memberBId = $value !== null && $value !== '' ? (int) $value : null;
    }

    /**
     * Server-side search for an eligible secondary member.
     *
     * Bounded by a result cap so the modal never ships the full same-type
     * member set to the client. User-type members are never offered.
     */
    public function updatedMergeSearch(string $value): void
    {
        $this->mergeSearchResults = [];

        if ($this->memberAId === null || mb_strlen($value) < 2) {
            return;
        }

        $memberA = Member::find($this->memberAId);

        if (! $memberA || $memberA->membership_type === MembershipType::User) {
            return;
        }

        $this->mergeSearchResults = Member::query()
            ->where('membership_type', $memberA->membership_type)
            ->where('id', '!=', $memberA->id)
            ->whereLike('name', '%'.addcslashes($value, '%_').'%', caseSensitive: false)
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name'])
            ->map(fn (Member $m): array => ['id' => $m->id, 'name' => $m->name])
            ->all();
    }

    public function selectMergeTarget(int $id): void
    {
        $this->memberBId = $id;
        $this->mergeSearch = '';
        $this->mergeSearchResults = [];
    }

    public function clearMergeTarget(): void
    {
        $this->memberBId = null;
        $this->mergeSearch = '';
        $this->mergeSearchResults = [];
    }

    public function merge(): void
    {
        if (! $this->primaryId || ! $this->memberAId || ! $this->memberBId) {
            session()->flash('error', 'Please select both members before merging.');

            return;
        }

        $secondaryId = $this->primaryId === $this->memberAId
            ? $this->memberBId
            : $this->memberAId;

        $succeeded = $this->runGuardedMerge(
            fn () => (new MergeMember)(MergeMemberData::validateAndCreate([
                'primary_id' => $this->primaryId,
                'secondary_id' => $secondaryId,
            ])),
            entityLabel: 'member',
            logContext: ['primary_id' => $this->primaryId, 'secondary_id' => $secondaryId],
        );

        if (! $succeeded) {
            return;
        }

        $this->dispatch('member-merged');
        $this->redirect(route('members.show', $this->primaryId), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $memberA = $this->memberAId ? Member::withCount(['addresses', 'emails', 'phones', 'links', 'attachments'])->find($this->memberAId) : null;
        $memberB = $this->memberBId ? Member::withCount(['addresses', 'emails', 'phones', 'links', 'attachments'])->find($this->memberBId) : null;

        // Whether the primary supports a secondary picker (same type, not user-type).
        $canPickSecondary = $this->needsSecondary
            && $memberA !== null
            && $memberA->membership_type !== MembershipType::User;

        return [
            'memberA' => $memberA,
            'memberB' => $memberB,
            'canPickSecondary' => $canPickSecondary,
        ];
    }

    public function render(): View
    {
        return view('livewire.members.merge-modal');
    }
}
