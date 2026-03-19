<?php

namespace App\Livewire\Members;

use App\Actions\Members\MergeMember;
use App\Data\Members\MergeMemberData;
use App\Models\Member;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;

class MergeModal extends Component
{
    public ?int $memberAId = null;

    public ?int $memberBId = null;

    public ?int $primaryId = null;

    #[On('open-merge-modal')]
    public function openModal(int $memberA, int $memberB): void
    {
        $this->memberAId = $memberA;
        $this->memberBId = $memberB;
        $this->primaryId = $memberA;
        $this->js("setTimeout(() => \$dispatch('open-modal', 'merge-members'), 50)");
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
            (new MergeMember)(MergeMemberData::from([
                'primary_id' => $this->primaryId,
                'secondary_id' => $secondaryId,
            ]));
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
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

        return [
            'memberA' => $memberA,
            'memberB' => $memberB,
        ];
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.members.merge-modal');
    }
}
