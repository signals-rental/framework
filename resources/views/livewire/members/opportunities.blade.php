<?php

use App\Models\Member;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links', 'organisations', 'contacts']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->member->name . ' — Opportunities');
    }
}; ?>

<section class="w-full">
    @include('livewire.members.partials.member-header', ['member' => $member, 'subpage' => 'Opportunities'])
    @include('livewire.members.partials.member-tabs', ['member' => $member, 'activeTab' => 'opportunities'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <x-signals.empty title="Opportunities Coming Soon" description="Opportunities for this member will appear here once the opportunity module is implemented.">
            <x-slot:icon>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-10 h-10 opacity-30"><path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            </x-slot:icon>
        </x-signals.empty>
    </div>
</section>
