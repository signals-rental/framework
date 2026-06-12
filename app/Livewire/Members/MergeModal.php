<?php

namespace App\Livewire\Members;

use App\Actions\Members\MergeMember;
use App\Data\Members\MergeMemberData;
use App\Enums\MembershipType;
use App\Models\Member;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class MergeModal extends Component
{
    public ?int $memberAId = null;

    public ?int $memberBId = null;

    public ?int $primaryId = null;

    /**
     * When opened from a member's page with only a primary member, the modal
     * shows a picker for the second member rather than a two-member comparison.
     */
    public bool $needsSecondary = false;

    #[On('open-merge-modal')]
    public function openModal(int $memberA, int $memberB): void
    {
        $this->memberAId = $memberA;
        // A memberB of 0 means "no second member chosen yet" — the modal opens
        // in select-secondary mode with this member pre-set as the primary.
        $this->memberBId = $memberB > 0 ? $memberB : null;
        $this->needsSecondary = $this->memberBId === null;
        $this->primaryId = $memberA;
        $this->js("setTimeout(() => \$dispatch('open-modal', 'merge-members'), 50)");
    }

    public function updatedMemberBId(mixed $value): void
    {
        $this->memberBId = $value !== null && $value !== '' ? (int) $value : null;
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

        try {
            (new MergeMember)(MergeMemberData::validateAndCreate([
                'primary_id' => $this->primaryId,
                'secondary_id' => $secondaryId,
            ]));
        } catch (ValidationException $e) {
            // Business-rule failures (type mismatch, self-merge) surface as a
            // validation error keyed on secondary_id. Show the first message.
            session()->flash('error', $e->validator->errors()->first() ?: 'Unable to merge the selected members.');

            return;
        } catch (ModelNotFoundException) {
            session()->flash('error', 'One of the selected members no longer exists.');

            return;
        } catch (\Throwable $e) {
            Log::error('Member merge failed', [
                'primary_id' => $this->primaryId,
                'secondary_id' => $secondaryId,
                'error' => $e->getMessage(),
            ]);
            session()->flash('error', 'An unexpected error occurred while merging. Please try again.');

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

        // Eligible secondaries: same type, not user-type, excluding the primary.
        $eligibleSecondaries = [];
        if ($this->needsSecondary && $memberA && $memberA->membership_type !== MembershipType::User) {
            $eligibleSecondaries = Member::query()
                ->where('membership_type', $memberA->membership_type)
                ->where('id', '!=', $memberA->id)
                ->orderBy('name')
                ->get()
                ->map(fn (Member $m): array => ['value' => $m->id, 'label' => $m->name])
                ->values()
                ->all();
        }

        return [
            'memberA' => $memberA,
            'memberB' => $memberB,
            'eligibleSecondaries' => $eligibleSecondaries,
        ];
    }

    public function render(): View
    {
        return view('livewire.members.merge-modal');
    }
}
