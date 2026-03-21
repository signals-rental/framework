<?php

use App\Models\Member;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount([
            'addresses', 'emails', 'phones', 'links', 'organisations', 'contacts', 'attachments',
        ]);
        $this->member->load(['saleTaxClass', 'purchaseTaxClass', 'organisations', 'contacts']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->member->name);
    }

}; ?>

<section class="w-full">
    @include('livewire.members.partials.member-header', ['member' => $member])

    @include('livewire.members.partials.member-tabs', ['member' => $member, 'activeTab' => 'overview'])

    {{-- 3-column layout --}}
    <div class="grid grid-cols-[240px_1fr_280px] gap-6 px-6 py-4 max-lg:grid-cols-[240px_1fr] max-md:grid-cols-1 max-md:px-5 max-sm:px-3">

        {{-- ============================================================ --}}
        {{-- LEFT SIDEBAR --}}
        {{-- ============================================================ --}}
        <div class="space-y-6">
            @if($member->description)
                <div class="rounded-lg border border-[var(--card-border)] bg-white px-3 py-2">
                    <p class="text-xs uppercase tracking-wide text-[var(--text-muted)]" style="font-family: var(--font-mono);">
                        {{ $member->description }}
                    </p>
                </div>
            @endif

            {{-- Quick Actions --}}
            <x-signals.sidebar title="Quick Actions" style="width: 100%;">
                <div class="s-sidebar-item" style="opacity: 0.5; cursor: default;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 flex-shrink-0"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <span class="s-sidebar-item-name">Send Email</span>
                </div>
                <div class="s-sidebar-item" style="opacity: 0.5; cursor: default;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 flex-shrink-0"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
                    <span class="s-sidebar-item-name">Log Call</span>
                </div>
                <div class="s-sidebar-item" style="opacity: 0.5; cursor: default;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 flex-shrink-0"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span class="s-sidebar-item-name">Create Quote</span>
                </div>
                <div class="s-sidebar-item" style="opacity: 0.5; cursor: default;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 flex-shrink-0"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span class="s-sidebar-item-name">Schedule Meeting</span>
                </div>
            </x-signals.sidebar>

            {{-- Key Contacts / Organisations --}}
            @if($member->membership_type === \App\Enums\MembershipType::Organisation || $member->membership_type === \App\Enums\MembershipType::Venue)
                <x-signals.panel title="Key Contacts">
                    <x-slot:headerActions>
                        <a href="{{ route('members.relationships.create', $member) }}" wire:navigate class="s-btn s-btn-xs s-btn-primary">Add</a>
                    </x-slot:headerActions>
                    @if($member->contacts->isNotEmpty())
                        <div class="space-y-3">
                            @foreach($member->contacts->take(5) as $contact)
                                <a href="{{ route('members.show', $contact) }}" wire:navigate class="flex items-center gap-3 group">
                                    @php
                                        $cWords = preg_split('/\s+/', trim($contact->name));
                                        $cInitials = mb_strtoupper(mb_substr($cWords[0] ?? '', 0, 1) . mb_substr($cWords[1] ?? '', 0, 1));
                                    @endphp
                                    <x-signals.avatar size="sm" :initials="$cInitials" color="blue" />
                                    <div>
                                        <div class="text-sm font-medium text-[var(--link)] group-hover:underline" style="font-family: var(--font-display);">{{ $contact->name }}</div>
                                        @if($contact->pivot->relationship_type)
                                            <div class="text-xs text-[var(--text-muted)]">{{ $contact->pivot->relationship_type }}</div>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                        @if($member->contacts_count > 5)
                            <div class="mt-3 pt-3 border-t border-[var(--card-border)]">
                                <a href="{{ route('members.contacts', $member) }}" wire:navigate class="text-xs text-[var(--link)] hover:underline">
                                    View all {{ $member->contacts_count }} contacts
                                </a>
                            </div>
                        @endif
                    @else
                        <p class="text-sm text-[var(--text-muted)]">No contacts linked.</p>
                    @endif
                </x-signals.panel>
            @else
                <x-signals.panel title="Organisations">
                    <x-slot:headerActions>
                        <a href="{{ route('members.relationships.create', $member) }}" wire:navigate class="s-btn s-btn-xs s-btn-primary">Add</a>
                    </x-slot:headerActions>
                    @if($member->organisations->isNotEmpty())
                        <div class="space-y-3">
                            @foreach($member->organisations->take(5) as $org)
                                <a href="{{ route('members.show', $org) }}" wire:navigate class="flex items-center gap-3 group">
                                    @php
                                        $oWords = preg_split('/\s+/', trim($org->name));
                                        $oInitials = mb_strtoupper(mb_substr($oWords[0] ?? '', 0, 1) . mb_substr($oWords[1] ?? '', 0, 1));
                                    @endphp
                                    <x-signals.avatar size="sm" :initials="$oInitials" color="amber" />
                                    <div>
                                        <div class="text-sm font-medium text-[var(--link)] group-hover:underline" style="font-family: var(--font-display);">{{ $org->name }}</div>
                                        @if($org->pivot->relationship_type)
                                            <div class="text-xs text-[var(--text-muted)]">{{ $org->pivot->relationship_type }}</div>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                        @if($member->organisations_count > 5)
                            <div class="mt-3 pt-3 border-t border-[var(--card-border)]">
                                <a href="{{ route('members.contacts', $member) }}" wire:navigate class="text-xs text-[var(--link)] hover:underline">
                                    View all {{ $member->organisations_count }} organisations
                                </a>
                            </div>
                        @endif
                    @else
                        <p class="text-sm text-[var(--text-muted)]">No organisations linked.</p>
                    @endif
                </x-signals.panel>
            @endif

            {{-- Account Details --}}
            <x-signals.panel title="Account Details">
                <x-signals.data-list layout="vertical" :items="[
                    ['label' => 'Customer Since', 'value' => $member->created_at?->format('F Y') ?? 'March 2019'],
                    ['label' => 'Lifetime Value', 'value' => '£284,500'],
                    ['label' => 'Orders (12mo)', 'value' => '24'],
                    ['label' => 'Avg Order Value', 'value' => '£11,854'],
                    ['label' => 'Payment Terms', 'value' => 'Net 30'],
                    ['label' => 'Last Order', 'value' => '8 days ago'],
                    ['label' => 'Last Contact', 'value' => 'Today'],
                ]" />
            </x-signals.panel>
        </div>

        {{-- ============================================================ --}}
        {{-- CENTER CONTENT --}}
        {{-- ============================================================ --}}
        <div class="space-y-6">
            {{-- Stat Cards --}}
            <x-signals.stat-grid style="grid-template-columns: repeat(3, 1fr);">
                <x-signals.stat-card label="Total CLV" value="£284,500" trend="+12%" :trendUp="true" color="green">
                    <x-slot:icon><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></x-slot:icon>
                    <x-slot:sparkline><x-signals.sparkline :data="[18, 22, 19, 25, 24, 28, 26, 31, 29, 34, 32, 36]" color="green" size="sm" /></x-slot:sparkline>
                </x-signals.stat-card>
                <x-signals.stat-card label="Active Orders" value="3" trend="+2" :trendUp="true" color="blue">
                    <x-slot:icon><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></x-slot:icon>
                    <x-slot:sparkline><x-signals.sparkline :data="[2, 3, 2, 4, 3, 3, 5, 4, 3, 4, 3, 3]" color="blue" size="sm" /></x-slot:sparkline>
                </x-signals.stat-card>
                <x-signals.stat-card label="YTD Revenue" value="£186,400" trend="+18%" :trendUp="true" color="green">
                    <x-slot:icon><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg></x-slot:icon>
                    <x-slot:sparkline><x-signals.sparkline :data="[8, 12, 10, 15, 14, 18, 16, 20, 19, 22, 21, 24]" color="green" size="sm" /></x-slot:sparkline>
                </x-signals.stat-card>
                <x-signals.stat-card label="Outstanding" value="£12,500" trend="-23%" :trendUp="false" color="amber">
                    <x-slot:icon><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></x-slot:icon>
                    <x-slot:sparkline><x-signals.sparkline :data="[15, 12, 18, 10, 14, 11, 9, 8, 12, 10, 8, 6]" color="red" size="sm" /></x-slot:sparkline>
                </x-signals.stat-card>
                <x-signals.stat-card label="Pending Quotes" value="2" color="amber">
                    <x-slot:icon><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></x-slot:icon>
                    <x-slot:sparkline><x-signals.sparkline :data="[3, 2, 4, 3, 2, 1, 3, 2, 2, 3, 2, 2]" color="neutral" size="sm" /></x-slot:sparkline>
                </x-signals.stat-card>
                <x-signals.stat-card label="Avg Payment" value="18 days" trend="-3d" :trendUp="true" color="green">
                    <x-slot:icon><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></x-slot:icon>
                    <x-slot:sparkline><x-signals.sparkline :data="[25, 22, 24, 20, 21, 19, 20, 18, 19, 17, 18, 18]" color="green" size="sm" /></x-slot:sparkline>
                </x-signals.stat-card>
            </x-signals.stat-grid>

            {{-- AI Recommendations --}}
            <x-signals.panel title="AI Recommendations">
                <div class="grid grid-cols-3 gap-3 max-md:grid-cols-1">
                    <div style="border-left: 3px solid var(--red); padding: 8px 12px; background: var(--s-red-bg);">
                        <div class="flex items-center justify-between mb-1">
                            <span class="s-badge s-badge-red">P1</span>
                            <span class="text-[10px] text-[var(--text-muted)]" style="font-family: var(--font-mono);">+&pound;32k</span>
                        </div>
                        <div class="text-sm font-semibold text-[var(--red)]" style="font-family: var(--font-display);">Send follow-up for pending quote</div>
                        <p class="text-xs text-[var(--text-secondary)] mt-1">QUO-2026-0389 has been pending for 14 days. Similar quotes have 34% higher conversion when followed up within 7 days.</p>
                    </div>
                    <div style="border-left: 3px solid var(--amber); padding: 8px 12px; background: var(--s-amber-bg);">
                        <div class="flex items-center justify-between mb-1">
                            <span class="s-badge s-badge-amber">P2</span>
                            <span class="text-[10px] text-[var(--text-muted)]" style="font-family: var(--font-mono);">+15% CLV</span>
                        </div>
                        <div class="text-sm font-semibold" style="font-family: var(--font-display); color: var(--amber);">Cross-sell audio equipment</div>
                        <p class="text-xs text-[var(--text-secondary)] mt-1">Customer rents LED walls but not audio. 72% of similar event customers also rent audio packages.</p>
                    </div>
                    <div style="border-left: 3px solid #2563eb; padding: 8px 12px; background: var(--s-blue-bg);">
                        <div class="flex items-center justify-between mb-1">
                            <span class="s-badge s-badge-blue">P3</span>
                            <span class="text-[10px] text-[var(--text-muted)]" style="font-family: var(--font-mono);">Retention</span>
                        </div>
                        <div class="text-sm font-semibold" style="font-family: var(--font-display); color: #2563eb;">Schedule Q1 planning call</div>
                        <p class="text-xs text-[var(--text-secondary)] mt-1">Strong relationship — secure early commitment for January events calendar.</p>
                    </div>
                </div>
            </x-signals.panel>

            {{-- Activity Timeline --}}
            <x-signals.panel title="Activity Timeline">
                {{-- Toolbar --}}
                <div class="flex items-center gap-3 mb-4">
                    <div class="s-search" style="width: 220px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" placeholder="Search activity...">
                    </div>
                    <div class="flex items-center gap-1">
                        <button class="s-chip on">All <span style="opacity: 0.6;">47</span></button>
                        <button class="s-chip">Orders <span style="opacity: 0.6;">18</span></button>
                        <button class="s-chip">Quotes <span style="opacity: 0.6;">12</span></button>
                        <button class="s-chip">Comms <span style="opacity: 0.6;">9</span></button>
                        <button class="s-chip">Finance <span style="opacity: 0.6;">8</span></button>
                    </div>
                </div>
                <x-signals.timeline>
                    <x-signals.timeline-item color="green" title="Invoice Paid" meta="Today, 11:42">
                        INV-2026-1284 paid in full — <span class="s-cell-amount">&pound;12,450.00</span>
                        <span class="s-badge s-badge-green" style="margin-left: 6px;">On Time</span>
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="blue" title="Invoice Issued" meta="Today, 09:15">
                        INV-2026-1284 issued for order OPP-2026-0891 — <span class="s-cell-amount">&pound;12,450.00</span>
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="green" title="Order Dispatched" meta="Yesterday, 16:30">
                        OPP-2026-0891 dispatched — 24 items via own transport
                        <span class="s-badge" style="margin-left: 6px;">Delivery</span>
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="blue" title="Order Checked Out" meta="Yesterday, 14:20">
                        OPP-2026-0891 checked out by warehouse team — all items scanned
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="green" title="Converted to Order" meta="3 days ago, 10:05">
                        QUO-2026-0456 converted to order OPP-2026-0891
                        <span class="s-badge s-badge-green" style="margin-left: 6px;">Won</span>
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="amber" title="Quote Revised" meta="3 days ago, 09:48">
                        QUO-2026-0456 v3 created — added 4x LED wash lights, revised total to &pound;12,450.00
                    </x-signals.timeline-item>
                    <x-signals.timeline-item title="Email Sent" meta="4 days ago, 15:12">
                        Revised quote PDF sent to sarah@example.com
                        <span class="s-badge s-badge-blue" style="margin-left: 6px;">Comms</span>
                    </x-signals.timeline-item>
                    <x-signals.timeline-item title="Note Added" meta="4 days ago, 14:55">
                        Client requested LED wash upgrade for main stage — budget approved
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="amber" title="Quote Sent" meta="5 days ago, 11:30">
                        QUO-2026-0456 v2 emailed to client — &pound;10,800.00
                    </x-signals.timeline-item>
                    <x-signals.timeline-item title="Phone Call Logged" meta="5 days ago, 11:15">
                        Discussed requirements with Sarah Johnson — needs extra lighting for outdoor stage
                        <span class="s-badge s-badge-blue" style="margin-left: 6px;">Comms</span>
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="blue" title="Item Quantity Changed" meta="6 days ago, 16:40">
                        QUO-2026-0456: Shure SM58 qty changed from 8 to 12
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="blue" title="Item Added to Quote" meta="6 days ago, 16:35">
                        QUO-2026-0456: Added 6x JBL EON615 Speaker (&pound;120.00/day)
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="blue" title="Item Added to Quote" meta="6 days ago, 16:32">
                        QUO-2026-0456: Added 12x Shure SM58 Microphone (&pound;5.00/day)
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="green" title="Quote Created" meta="6 days ago, 16:30">
                        QUO-2026-0456 created — Summer Festival Main Stage
                    </x-signals.timeline-item>
                    <x-signals.timeline-item title="Logged In" meta="6 days ago, 16:28">
                        Web portal login from 192.168.1.42
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="green" title="Payment Received" meta="1 week ago, 09:00">
                        INV-2026-1180 paid — <span class="s-cell-amount">&pound;8,200.00</span>
                        <span class="s-badge s-badge-green" style="margin-left: 6px;">On Time</span>
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="blue" title="Invoice Issued" meta="2 weeks ago, 10:30">
                        INV-2026-1180 issued for OPP-2026-0834 — <span class="s-cell-amount">&pound;8,200.00</span>
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="green" title="Order Returned" meta="2 weeks ago, 08:45">
                        OPP-2026-0834 — all 18 items returned, condition checked OK
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="green" title="Order Dispatched" meta="3 weeks ago, 07:15">
                        OPP-2026-0834 dispatched — 18 items to ExCeL London
                    </x-signals.timeline-item>
                    <x-signals.timeline-item title="Delivery Note Signed" meta="3 weeks ago, 09:30">
                        Signed by James Thompson at venue
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="blue" title="Crew Assigned" meta="3 weeks ago, 14:00">
                        OPP-2026-0834: 2 crew members assigned for setup (Mike R, Dave S)
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="green" title="Converted to Order" meta="4 weeks ago, 11:20">
                        QUO-2026-0389 converted to order OPP-2026-0834
                        <span class="s-badge s-badge-green" style="margin-left: 6px;">Won</span>
                    </x-signals.timeline-item>
                    <x-signals.timeline-item title="Email Received" meta="4 weeks ago, 10:55">
                        Client confirmed go-ahead via email
                        <span class="s-badge s-badge-blue" style="margin-left: 6px;">Comms</span>
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="amber" title="Quote Follow-up" meta="5 weeks ago, 09:00">
                        Automated follow-up sent for QUO-2026-0389
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="amber" title="Quote Sent" meta="6 weeks ago, 14:45">
                        QUO-2026-0389 emailed — Corporate Awards Dinner — &pound;8,200.00
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="blue" title="Added to Basket" meta="6 weeks ago, 14:30">
                        Web portal: 4x Stage Deck 4x8, 2x Truss Section 3m, 8x PAR64 LED
                    </x-signals.timeline-item>
                    <x-signals.timeline-item title="Logged In" meta="6 weeks ago, 14:25">
                        Web portal login from 10.0.0.15
                    </x-signals.timeline-item>
                    <x-signals.timeline-item title="Meeting Scheduled" meta="7 weeks ago, 16:00">
                        Site visit at ExCeL London — Rachel Green attending
                        <span class="s-badge s-badge-blue" style="margin-left: 6px;">Comms</span>
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="red" title="Quote Declined" meta="8 weeks ago, 11:30">
                        QUO-2026-0301 declined — client went with competitor for lighting package
                        <span class="s-badge s-badge-red" style="margin-left: 6px;">Lost</span>
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="amber" title="Quote Sent" meta="9 weeks ago, 10:15">
                        QUO-2026-0301 emailed — Gaming Expo Setup — &pound;32,000.00
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="blue" title="Sub-hire Requested" meta="9 weeks ago, 09:50">
                        QUO-2026-0301: 20x moving head lights requested from Stage Solutions Ltd
                    </x-signals.timeline-item>
                    <x-signals.timeline-item title="Phone Call Logged" meta="9 weeks ago, 09:30">
                        Initial enquiry from Sarah — large AV setup for gaming expo
                        <span class="s-badge s-badge-blue" style="margin-left: 6px;">Comms</span>
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="green" title="Payment Received" meta="10 weeks ago, 14:20">
                        INV-2026-0934 paid — <span class="s-cell-amount">&pound;68,000.00</span>
                        <span class="s-badge s-badge-green" style="margin-left: 6px;">On Time</span>
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="amber" title="Payment Reminder Sent" meta="10 weeks ago, 09:00">
                        Automated reminder for INV-2026-0934 (due in 3 days)
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="blue" title="Invoice Issued" meta="11 weeks ago, 11:00">
                        INV-2026-0934 issued for Summer Festival 2025 — <span class="s-cell-amount">&pound;68,000.00</span>
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="green" title="Order Returned" meta="11 weeks ago, 08:00">
                        OPP-2025-0612 — 142 items returned, 2 items flagged for inspection
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="amber" title="Damage Reported" meta="11 weeks ago, 10:30">
                        OPP-2025-0612: 1x JBL VTX speaker cabinet — dent on rear panel
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="green" title="Order Dispatched" meta="12 weeks ago, 06:00">
                        OPP-2025-0612 dispatched — 142 items across 3 vehicles
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="blue" title="Transport Arranged" meta="12 weeks ago, 14:00">
                        3 vehicles booked for OPP-2025-0612: 2x 7.5t + 1x Sprinter
                    </x-signals.timeline-item>
                    <x-signals.timeline-item title="Document Uploaded" meta="13 weeks ago, 16:20">
                        Risk assessment uploaded for Summer Festival venue
                    </x-signals.timeline-item>
                    <x-signals.timeline-item title="Contact Added" meta="13 weeks ago, 15:00">
                        James Thompson added as Technical Director
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="green" title="Converted to Order" meta="14 weeks ago, 09:45">
                        QUO-2025-0198 converted to order OPP-2025-0612
                        <span class="s-badge s-badge-green" style="margin-left: 6px;">Won</span>
                    </x-signals.timeline-item>
                    <x-signals.timeline-item title="Credit Check Passed" meta="14 weeks ago, 09:30">
                        Automated credit check — approved for &pound;75,000 limit
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="amber" title="Quote Revised" meta="15 weeks ago, 13:00">
                        QUO-2025-0198 v2 — added video wall, revised to &pound;68,000.00
                    </x-signals.timeline-item>
                    <x-signals.timeline-item color="amber" title="Quote Created" meta="16 weeks ago, 10:00">
                        QUO-2025-0198 created — Summer Festival 2025 Full AV Package — &pound;52,400.00
                    </x-signals.timeline-item>
                    <x-signals.timeline-item title="Organisation Created" meta="18 weeks ago, 14:30">
                        Member record created — Organisation type
                    </x-signals.timeline-item>
                </x-signals.timeline>

                {{-- Pagination --}}
                <div class="flex items-center justify-between mt-4 pt-3 border-t border-[var(--card-border)]">
                    <span class="text-xs text-[var(--text-muted)]" style="font-family: var(--font-mono);">Showing 1–5 of 47 events</span>
                    <div class="flex items-center gap-1">
                        <button class="s-pagination-btn" disabled style="opacity: 0.4;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>
                        <button class="s-pagination-btn" style="background: var(--green); color: white; font-weight: 600;">1</button>
                        <button class="s-pagination-btn">2</button>
                        <button class="s-pagination-btn">3</button>
                        <span class="s-pagination-ellipsis">&hellip;</span>
                        <button class="s-pagination-btn">10</button>
                        <button class="s-pagination-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    </div>
                </div>
            </x-signals.panel>

        </div>

        {{-- ============================================================ --}}
        {{-- RIGHT SIDEBAR --}}
        {{-- ============================================================ --}}
        <div class="space-y-6 max-lg:col-span-full max-lg:grid max-lg:grid-cols-2 max-lg:gap-6 max-md:grid-cols-1">
            {{-- Customer Health Score --}}
            <div class="text-center" style="background: var(--card-bg); border: 1px solid var(--card-border); padding: 20px; box-shadow: var(--shadow-card);">
                <svg viewBox="0 0 120 120" class="mx-auto" style="width: 100px; height: 100px;">
                    <circle cx="60" cy="60" r="50" fill="none" stroke="var(--card-border)" stroke-width="10" />
                    <circle cx="60" cy="60" r="50" fill="none" stroke="#16a34a" stroke-width="10"
                        stroke-dasharray="280 314" stroke-dashoffset="0"
                        stroke-linecap="round" transform="rotate(-90 60 60)" />
                    <text x="60" y="65" text-anchor="middle" style="font-family: var(--font-display); font-size: 28px; font-weight: 700; fill: var(--text-primary);">89</text>
                </svg>
                <p class="mt-1 text-[9px] uppercase tracking-widest text-[var(--text-muted)]" style="font-family: var(--font-mono);">Customer Health</p>
                <span class="s-badge s-badge-green mt-1">Excellent</span>
            </div>

            {{-- Health Factors --}}
            <x-signals.panel title="Health Factors">
                <div class="space-y-3">
                    <x-signals.progress label="Revenue Trend" :percent="92" />
                    <x-signals.progress label="Engagement Level" :percent="88" />
                    <x-signals.progress label="Payment History" :percent="95" />
                    <x-signals.progress label="Order Frequency" :percent="86" />
                    <x-signals.progress label="Product Breadth" :percent="68" />
                </div>
            </x-signals.panel>

        </div>
    </div>
</section>
