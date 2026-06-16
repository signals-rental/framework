<?php

use App\Livewire\Concerns\HasAuditTimeline;
use App\Models\Member;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    use HasAuditTimeline;

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

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        // Resolve related members across both relationship directions so a link
        // appears on each member regardless of how it was created.
        $relationships = $this->member->allRelationships();

        $relatedMembers = $relationships
            ->map(fn ($r) => tap($this->member->counterpartIn($r), function ($other) use ($r): void {
                if ($other) {
                    $other->setRelation('pivot', (object) [
                        'relationship_type' => $r->relationship_type,
                        'is_primary' => $r->is_primary,
                    ]);
                }
            }))
            ->filter()
            ->values();

        return [
            'relatedMembers' => $relatedMembers,
            'relatedCount' => $relatedMembers->count(),
            'timeline' => $this->auditTimelineFor($this->member),
        ];
    }

    /**
     * Member-specific timeline colours: archive/anonymise read as destructive
     * (red), merges as a change (blue).
     *
     * @return array<string, list<string>>
     */
    protected function timelineColorMap(): array
    {
        return [
            'green' => ['.created', '.restored'],
            'red' => ['.deleted', '.archived', '.anonymised'],
            'blue' => ['.updated', '.merged'],
        ];
    }
}; ?>

<section class="w-full">
    @include('livewire.members.partials.member-header', ['member' => $member])

    {{-- Hosts the "Merge with..." flow from the header New menu. --}}
    <livewire:members.merge-modal />

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

            {{-- Related members (contacts / organisations / venues), both directions --}}
            @php
                $isOrgOrVenue = $member->membership_type === \App\Enums\MembershipType::Organisation
                    || $member->membership_type === \App\Enums\MembershipType::Venue;
                $relatedPanelTitle = $isOrgOrVenue ? 'Key Contacts' : 'Organisations';
                $relatedEmptyText = $isOrgOrVenue ? 'No contacts linked.' : 'No organisations linked.';
            @endphp
            <x-signals.panel :title="$relatedPanelTitle">
                <x-slot:headerActions>
                    <a href="{{ route('members.relationships.create', $member) }}" wire:navigate class="s-btn s-btn-xs s-btn-primary">Add</a>
                </x-slot:headerActions>
                @if($relatedMembers->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($relatedMembers->take(5) as $related)
                            <a href="{{ route('members.show', $related) }}" wire:navigate class="flex items-center gap-3 group" wire:key="related-{{ $related->id }}">
                                @php
                                    $rWords = preg_split('/\s+/', trim($related->name));
                                    $rInitials = mb_strtoupper(mb_substr($rWords[0] ?? '', 0, 1) . mb_substr($rWords[1] ?? '', 0, 1));
                                    $rColor = $related->membership_type === \App\Enums\MembershipType::Contact ? 'blue' : 'amber';
                                    $rIconSrc = null;
                                    if ($related->icon_thumb_url) {
                                        try {
                                            $rIconSrc = app(\App\Services\FileService::class)->signedUrl($related->icon_thumb_url);
                                        } catch (\Throwable) {
                                            // Fall back to initials
                                        }
                                    }
                                @endphp
                                <x-signals.avatar size="sm" :src="$rIconSrc" :initials="$rInitials" :color="$rColor" />
                                <div>
                                    <div class="text-sm font-medium text-[var(--link)] group-hover:underline" style="font-family: var(--font-display);">{{ $related->name }}</div>
                                    @if($related->pivot->relationship_type)
                                        <div class="text-xs text-[var(--text-muted)]">{{ $related->pivot->relationship_type }}</div>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                    @if($relatedCount > 5)
                        <div class="mt-3 pt-3 border-t border-[var(--card-border)]">
                            <a href="{{ route('members.contacts', $member) }}" wire:navigate class="text-xs text-[var(--link)] hover:underline">
                                View all {{ $relatedCount }} related members
                            </a>
                        </div>
                    @endif
                @else
                    <p class="text-sm text-[var(--text-muted)]">{{ $relatedEmptyText }}</p>
                @endif
            </x-signals.panel>

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

            {{-- Activity Timeline (live audit trail) --}}
            <x-signals.panel title="Activity Timeline">
                @if($timeline->isEmpty())
                    <div class="text-sm text-[var(--text-muted)] py-4">No recorded activity for this member yet.</div>
                @else
                    <x-signals.timeline>
                        @foreach($timeline as $event)
                            <x-signals.timeline-item
                                :color="$event['color']"
                                :title="$event['title']"
                                :meta="$event['meta']"
                                wire:key="timeline-{{ $loop->index }}"
                            >
                                @if($event['body'])
                                    {{ $event['body'] }}
                                @endif
                            </x-signals.timeline-item>
                        @endforeach
                    </x-signals.timeline>
                @endif
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
