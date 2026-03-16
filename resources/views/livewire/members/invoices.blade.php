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
        $view->title($this->member->name . ' — Invoices');
    }
}; ?>

<section class="w-full">
    @include('livewire.members.partials.member-header', ['member' => $member, 'subpage' => 'Invoices'])
    @include('livewire.members.partials.member-tabs', ['member' => $member, 'activeTab' => 'invoices'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <x-signals.empty title="Invoices Coming Soon" description="Invoices for this member will appear here once the invoicing module is implemented.">
            <x-slot:icon>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-10 h-10 opacity-30"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </x-slot:icon>
        </x-signals.empty>
    </div>
</section>
