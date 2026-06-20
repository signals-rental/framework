@php
    $stateBadgeClass = match($item->state) {
        \App\Enums\OpportunityState::Draft => 's-badge-zinc',
        \App\Enums\OpportunityState::Quotation => 's-badge-blue',
        \App\Enums\OpportunityState::Order => 's-badge-green',
        default => 's-badge-zinc',
    };
@endphp
<span class="s-badge {{ $stateBadgeClass }}">{{ $item->state->label() }}</span>
