<div>
    @if($showModal)
    <div class="s-modal-backdrop" x-data x-transition.opacity x-on:keydown.escape.window="$wire.close()">
        <div class="s-modal-lg s-modal" x-on:click.outside="$wire.close()">
            {{-- Header --}}
            <div class="s-modal-header">
                <span class="s-modal-title">{{ $editingViewId ? 'Edit Custom View' : 'New Custom View' }}</span>
                <button class="s-modal-close" type="button" wire:click="close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="s-modal-body flex flex-col gap-5">
                {{-- Name + Visibility row --}}
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="name" label="Name" placeholder="View name..." required />
                    <flux:select wire:model="visibility" label="Visibility">
                        <option value="personal">Personal (only me)</option>
                        <option value="shared">Shared (by role)</option>
                    </flux:select>
                </div>

                {{-- Columns: Two-panel selector --}}
                <div class="s-field">
                    <label class="s-field-label">Columns</label>
                    <div class="grid grid-cols-2 gap-3">
                        {{-- Available --}}
                        <div class="rounded-md border border-[var(--card-border)] p-2">
                            <div class="px-1 pb-1.5 pt-0.5 font-[var(--font-mono)] text-[9px] font-medium uppercase tracking-wider text-[var(--text-muted)]">Available</div>
                            <div class="max-h-[200px] overflow-y-auto">
                                @foreach($this->availableColumns as $col)
                                    <button wire:click="addColumn('{{ $col->key }}')" wire:key="available-{{ $col->key }}"
                                        class="s-dropdown-item w-full justify-between">
                                        {{ $col->label }}
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-2.5 opacity-40"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Selected --}}
                        <div class="rounded-md border border-[var(--card-border)] p-2">
                            <div class="px-1 pb-1.5 pt-0.5 font-[var(--font-mono)] text-[9px] font-medium uppercase tracking-wider text-[var(--text-muted)]">Selected ({{ count($selectedColumns) }})</div>
                            <div class="max-h-[200px] overflow-y-auto">
                                @foreach($this->selectedColumnDetails as $index => $col)
                                    <div wire:key="selected-{{ $col['key'] }}-{{ $index }}"
                                        class="s-chip s-chip-green mb-0.5 flex items-center gap-1 px-2 py-1.5">
                                        <span class="flex-1 text-[13px]">{{ $col['label'] }}</span>
                                        <button wire:click="moveUp({{ $index }})" class="border-0 bg-transparent p-0 {{ $index === 0 ? 'opacity-20 cursor-default' : 'opacity-50 cursor-pointer' }}" @if($index === 0) disabled @endif>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-2.5"><polyline points="18 15 12 9 6 15"/></svg>
                                        </button>
                                        <button wire:click="moveDown({{ $index }})" class="border-0 bg-transparent p-0 {{ $index === count($selectedColumns) - 1 ? 'opacity-20 cursor-default' : 'opacity-50 cursor-pointer' }}" @if($index === count($selectedColumns) - 1) disabled @endif>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-2.5"><polyline points="6 9 12 15 18 9"/></svg>
                                        </button>
                                        <button wire:click="removeColumn({{ $index }})" class="cursor-pointer border-0 bg-transparent p-0 text-[var(--red)] opacity-50">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Filters with per-filter logic --}}
                <div class="s-field">
                    <label class="s-field-label">Filters</label>

                    <div class="flex flex-col gap-0">
                        @foreach($filters as $index => $filter)
                            {{-- Logic connector between filters --}}
                            @if($index > 0)
                                <div class="flex items-center gap-1.5 py-0.5">
                                    <div class="shrink-0">
                                        <select wire:model.live="filters.{{ $index }}.logic" class="s-input w-auto px-1.5 py-0.5 font-[var(--font-display)] text-[10px] font-semibold uppercase tracking-wide">
                                            <option value="and">AND</option>
                                            <option value="or">OR</option>
                                            <option value="nand">NAND</option>
                                            <option value="nor">NOR</option>
                                        </select>
                                    </div>
                                    <div class="h-px flex-1 bg-[var(--s-border-sub)]"></div>
                                </div>
                            @endif

                            {{-- Filter row --}}
                            <div wire:key="filter-{{ $index }}" class="flex items-center gap-1.5">
                                <flux:select wire:model="filters.{{ $index }}.field" class="flex-1">
                                    <option value="">Select field...</option>
                                    @foreach($this->filterableFields as $field)
                                        <option value="{{ $field['key'] }}">{{ $field['label'] }}</option>
                                    @endforeach
                                </flux:select>
                                <flux:select wire:model="filters.{{ $index }}.predicate" class="w-[120px] shrink-0">
                                    <option value="eq">equals</option>
                                    <option value="not_eq">not equal</option>
                                    <option value="cont">contains</option>
                                    <option value="not_cont">not contain</option>
                                    <option value="gt">greater than</option>
                                    <option value="lt">less than</option>
                                    <option value="true">is true</option>
                                    <option value="false">is false</option>
                                    <option value="null">is empty</option>
                                    <option value="not_null">is not empty</option>
                                </flux:select>
                                <flux:input wire:model="filters.{{ $index }}.value" class="flex-1" placeholder="Value..." />
                                <button wire:click="removeFilter({{ $index }})" class="s-btn s-btn-xs s-btn-danger shrink-0">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                    <button wire:click="addFilter" class="s-btn s-btn-xs s-btn-ghost mt-1.5">
                        + Add criteria
                    </button>
                </div>

                {{-- Sort & Per Page --}}
                <div class="grid grid-cols-3 gap-4">
                    <flux:select wire:model="sortColumn" label="Sort by">
                        <option value="">None</option>
                        @foreach($this->sortableFields as $field)
                            <option value="{{ $field['key'] }}">{{ $field['label'] }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="sortDirection" label="Direction">
                        <option value="asc">Ascending</option>
                        <option value="desc">Descending</option>
                    </flux:select>
                    <flux:select wire:model="perPage" label="Per page">
                        <option value="12">12</option>
                        <option value="20">20</option>
                        <option value="48">48</option>
                        <option value="100">100</option>
                    </flux:select>
                </div>
            </div>

            {{-- Footer --}}
            <div class="s-modal-footer">
                <button wire:click="close" class="s-btn s-btn-sm">Cancel</button>
                <button wire:click="save" class="s-btn s-btn-sm s-btn-primary">
                    {{ $editingViewId ? 'Update' : 'Create' }}
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
