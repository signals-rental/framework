<div x-data="{ pageIds: @js(collect($items->items())->pluck('id')->map(fn ($id) => (int) $id)->all()) }">
    {{-- Toolbar: search + actions --}}
    <div class="flex items-center gap-3 mb-3">
        @if(count($searchable) > 0)
            <div class="s-search" style="width: 50%;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" placeholder="Search..." wire:model.live.debounce.300ms="search">
            </div>
        @endif

        <div class="flex items-center gap-2 ml-auto">
            @if($activeFilterCount > 0)
                <button wire:click="clearAllFilters" class="s-btn s-btn-ghost s-btn-xs">
                    <flux:icon.x-mark class="w-3 h-3" />
                    Clear {{ $activeFilterCount }} {{ Str::plural('filter', $activeFilterCount) }}
                </button>
            @endif
            @if($toolbarView)
                @include($toolbarView)
            @endif
        </div>
    </div>

    {{-- Table --}}
    <div class="s-table-wrap">
        <table class="s-table">
            <thead>
                {{-- Column headers --}}
                <tr>
                    @foreach($columns as $col)
                        @if(($col['type'] ?? null) === 'checkbox')
                            <th class="s-col-check">
                                <x-signals.checkbox
                                    :checked="$selectAll"
                                    wire:click="toggleSelectAll"
                                    style="cursor: pointer;"
                                />
                            </th>
                        @elseif(($col['key'] ?? '') === 'avatar')
                            <th class="w-[40px]"></th>
                        @elseif(($col['type'] ?? null) === 'actions')
                            <th class="w-[60px]"></th>
                        @elseif($col['sortable'] ?? false)
                            <th
                                class="sortable {{ $sortField === $col['key'] ? ($sortDirection === 'asc' ? 'sort-asc' : 'sort-desc') : '' }}"
                                wire:click="sortBy('{{ $col['key'] }}')"
                            >
                                {{ $col['label'] ?? $col['key'] }}
                                <span class="s-sort-icon">
                                    @if($sortField === $col['key'] && $sortDirection === 'asc')
                                        <svg viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="1 5 5 1 9 5"/></svg>
                                    @elseif($sortField === $col['key'] && $sortDirection === 'desc')
                                        <svg viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="1 1 5 5 9 1"/></svg>
                                    @else
                                        <svg viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="1 7 5 3 9 7"/></svg>
                                    @endif
                                </span>
                            </th>
                        @else
                            <th>{{ $col['label'] ?? $col['key'] }}</th>
                        @endif
                    @endforeach
                </tr>

                {{-- Filter row --}}
                @if(collect($columns)->contains(fn ($col) => $col['filterable'] ?? false))
                    <tr class="s-table-filter">
                        @foreach($columns as $col)
                            @if(($col['type'] ?? null) === 'checkbox' || ($col['type'] ?? null) === 'actions' || ($col['key'] ?? '') === 'avatar')
                                <td></td>
                            @elseif($col['filterable'] ?? false)
                                <td>
                                    @if(($col['filter_type'] ?? 'text') === 'select')
                                        <select wire:change="applyFilter('{{ $col['key'] }}', $event.target.value)">
                                            <option value="">All</option>
                                            @foreach(($col['filter_options'] ?? []) as $optValue => $optLabel)
                                                <option value="{{ $optValue }}" @selected(($filters[$col['key']] ?? '') === (string) $optValue)>
                                                    {{ $optLabel }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input
                                            type="text"
                                            placeholder="Filter..."
                                            wire:model.live.debounce.300ms="filters.{{ $col['key'] }}"
                                        />
                                    @endif
                                </td>
                            @else
                                <td></td>
                            @endif
                        @endforeach
                    </tr>
                @endif
            </thead>

            <tbody wire:loading.class="s-loading" wire:target="search,sortBy,applyFilter,clearAllFilters,gotoPage,previousPage,nextPage,setPerPage,filters,refresh" style="user-select: none;">
                @forelse($items as $item)
                    <tr
                        wire:key="row-{{ $item->id }}"
                        class="{{ in_array($item->id, $selected) ? 'selected' : '' }}"
                        x-on:click="if (!$event.target.closest('a, button, .s-dropdown, .s-checkbox')) { $event.shiftKey ? $wire.shiftSelect({{ $item->id }}, pageIds) : $wire.toggleSelected({{ $item->id }}); }"
                        style="cursor: pointer;"
                    >
                        @foreach($columns as $col)
                            @if(($col['type'] ?? null) === 'checkbox')
                                <td class="s-col-check">
                                    <x-signals.checkbox
                                        :checked="in_array($item->id, $selected)"
                                        x-on:click="$event.shiftKey ? $wire.shiftSelect({{ $item->id }}, pageIds) : $wire.toggleSelected({{ $item->id }})"
                                        style="cursor: pointer;"
                                    />
                                </td>
                            @elseif(($col['type'] ?? null) === 'actions')
                                <td class="text-right">
                                    @if($actionsView)
                                        <div x-data="{ open: false }" class="relative inline-flex">
                                            <button x-on:click.stop="open = !open" class="s-btn-ghost s-btn-xs s-btn-icon">
                                                <svg viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
                                            </button>
                                            <div
                                                x-show="open"
                                                x-on:click.outside="open = false"
                                                x-transition:enter="transition ease-out duration-100"
                                                x-transition:enter-start="opacity-0 scale-95"
                                                x-transition:enter-end="opacity-100 scale-100"
                                                x-transition:leave="transition ease-in duration-75"
                                                x-transition:leave-start="opacity-100 scale-100"
                                                x-transition:leave-end="opacity-0 scale-95"
                                                x-cloak
                                                class="s-dropdown"
                                                style="position: fixed; z-index: 9999;"
                                                x-ref="dropdown"
                                                x-init="$watch('open', value => {
                                                    if (value) {
                                                        $nextTick(() => {
                                                            const btn = $el.previousElementSibling;
                                                            const rect = btn.getBoundingClientRect();
                                                            $refs.dropdown.style.top = rect.bottom + 4 + 'px';
                                                            $refs.dropdown.style.right = (window.innerWidth - rect.right) + 'px';
                                                            $refs.dropdown.style.left = 'auto';
                                                        });
                                                    }
                                                })"
                                            >
                                                @include($actionsView, ['item' => $item])
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            @else
                                <td @if(($col['key'] ?? '') === 'avatar') class="!px-0 !w-[40px]" @endif>
                                    @if(isset($col['view']))
                                        @include($col['view'], ['item' => $item, 'column' => $col])
                                    @else
                                        {{ $item->{$col['key']} ?? '' }}
                                    @endif
                                </td>
                            @endif
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" class="text-center py-12">
                            <div class="flex flex-col items-center gap-2 text-[var(--text-muted)]">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-10 h-10 opacity-30">
                                    <path d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                                </svg>
                                <span class="text-sm">{{ $emptyMessage }}</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Summary bar --}}
        <div class="s-summary-bar">
            <span>
                Showing
                <span class="s-summary-val">{{ count($columns) - collect($columns)->filter(fn ($c) => in_array($c['type'] ?? null, ['checkbox', 'actions']) || ($c['key'] ?? '') === 'avatar')->count() }}</span>
                columns
            </span>
            <span style="width: 1px; height: 14px; background: var(--card-border);"></span>
            <span>
                <span class="s-summary-val">{{ $activeFilterCount }}</span>
                {{ Str::plural('filter', $activeFilterCount) }} active
            </span>
            <span style="width: 1px; height: 14px; background: var(--card-border);"></span>
            <span>
                Sorted by
                <span class="s-summary-val">{{ $sortField ?: 'none' }}</span>
                <span class="s-summary-val">{{ $sortDirection }}</span>
            </span>
            <span style="width: 1px; height: 14px; background: var(--card-border);"></span>
            <span>
                <span class="s-summary-val">{{ $perPage }}</span>
                per page
            </span>
        </div>
    </div>

    {{-- Pagination + per-page selector --}}
    <div style="margin-top: 12px; display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-muted);">
            <span>Show:</span>
            <select wire:change="setPerPage($event.target.value)" style="background: var(--card-bg); border: 1px solid var(--card-border); color: var(--text-primary); font-size: 12px; padding: 4px 8px; cursor: pointer; outline: none;">
                @foreach($perPageOptions as $option)
                    <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
                @endforeach
            </select>
            <span>of {{ number_format($totalCount) }}</span>
        </div>
        <x-signals.pagination :paginator="$items" :per-page-options="[]" />
    </div>

    {{-- Bulk action bar (fixed to bottom) --}}
    @if(count($selected) > 0)
        <div class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 shadow-lg rounded-lg overflow-hidden">
            <x-signals.bulk-bar :count="count($selected)">
                @if($bulkActionsView)
                    @include($bulkActionsView, ['selected' => $selected])
                @endif
                <button wire:click="clearSelection" class="s-bulk-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    Clear selection
                </button>
            </x-signals.bulk-bar>
        </div>
    @endif
</div>
