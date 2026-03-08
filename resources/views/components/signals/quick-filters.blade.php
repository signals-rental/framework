@props([
    'filters' => [],
    'active' => null,
])

<div {{ $attributes->merge(['class' => 's-quick-filters']) }}>
    @foreach($filters as $filter)
        <button
            class="s-quick-filter {{ ($active === ($filter['value'] ?? $filter['label'])) ? 'active' : '' }}"
            type="button"
            wire:click="$set('filter', '{{ $filter['value'] ?? $filter['label'] }}')"
        >
            {{ $filter['label'] }}
            @if(isset($filter['count']))
                <span class="s-quick-filter-count">{{ $filter['count'] }}</span>
            @endif
        </button>
    @endforeach
</div>
