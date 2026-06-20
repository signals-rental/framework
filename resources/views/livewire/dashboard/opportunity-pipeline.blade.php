<div>
    @if ($canAccess)
        <h2 class="section-heading">Opportunity Pipeline</h2>
        <x-signals.stat-grid class="mb-8">
            <a href="{{ route('opportunities.index', ['state' => \App\Enums\OpportunityState::Quotation->value]) }}"
               wire:navigate
               class="s-stat-card">
                <div class="s-stat-icon s-stat-icon-blue">
                    <flux:icon.document-text />
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div class="s-stat-label">Open Quotations</div>
                    <div class="s-stat-value">{{ number_format($counts['quotations']) }}</div>
                </div>
            </a>

            <a href="{{ route('opportunities.index', ['state' => \App\Enums\OpportunityState::Order->value]) }}"
               wire:navigate
               class="s-stat-card">
                <div class="s-stat-icon s-stat-icon-green">
                    <flux:icon.briefcase />
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div class="s-stat-label">Live Orders</div>
                    <div class="s-stat-value">{{ number_format($counts['orders']) }}</div>
                </div>
            </a>

            <a href="{{ route('opportunities.index', ['state' => \App\Enums\OpportunityState::Order->value]) }}"
               wire:navigate
               class="s-stat-card">
                <div class="s-stat-icon s-stat-icon-violet">
                    <flux:icon.truck />
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div class="s-stat-label">Due to Dispatch</div>
                    <div class="s-stat-value">{{ number_format($counts['due_soon']) }}</div>
                    <div class="s-stat-label" style="margin-top: 2px; text-transform: none; letter-spacing: 0;">
                        Next {{ $dueSoonDays }} days
                    </div>
                </div>
            </a>

            <a href="{{ route('opportunities.index', ['has_shortage' => 1]) }}"
               wire:navigate
               class="s-stat-card">
                <div class="s-stat-icon s-stat-icon-amber">
                    <flux:icon.exclamation-triangle />
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div class="s-stat-label">With Shortages</div>
                    <div class="s-stat-value">{{ number_format($counts['shortages']) }}</div>
                </div>
            </a>
        </x-signals.stat-grid>
    @endif
</div>
