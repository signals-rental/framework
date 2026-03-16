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
        $view->title($this->member->name . ' — Movements');
    }
}; ?>

<section class="w-full">
    @include('livewire.members.partials.member-header', ['member' => $member, 'subpage' => 'Movements'])
    @include('livewire.members.partials.member-tabs', ['member' => $member, 'activeTab' => 'movements'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <x-signals.empty title="Movements Coming Soon" description="Stock movements for this member will appear here once the inventory module is implemented.">
            <x-slot:icon>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-10 h-10 opacity-30"><path d="M8 7h12l-2 6H6L4 3H2"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
            </x-slot:icon>
        </x-signals.empty>
    </div>
</section>
