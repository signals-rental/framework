@props([
    'name' => 'phone',
    'value' => '',
    'countryCode' => null,
    'countryCodeName' => 'country_code',
    'countries' => [],
    'label' => 'Phone Number',
])

<div>
    @if($label)
        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ $label }}</label>
    @endif
    <div
        class="flex gap-2"
        x-data="{
            open: false,
            search: '',
            code: @js($countryCode),
            countries: @js($countries),
            get filtered() {
                if (!this.search) return this.countries;
                const q = this.search.toLowerCase();
                return this.countries.filter(c =>
                    c.name.toLowerCase().includes(q) || c.iso2.toLowerCase().includes(q) || c.phone_prefix.includes(q)
                );
            },
            get selected() { return this.countries.find(c => c.iso2 === this.code); },
            get flag() {
                if (!this.code) return '🌐';
                return [...this.code.toUpperCase()].map(c => String.fromCodePoint(0x1F1E6 + c.charCodeAt(0) - 65)).join('');
            },
            select(c) { this.code = c.iso2; this.open = false; this.search = ''; $dispatch('input'); },
        }"
        x-on:click.outside="open = false"
    >
        <input type="hidden" name="{{ $countryCodeName }}" x-bind:value="code">
        <div class="relative" style="width: 110px; flex-shrink: 0;">
            <button
                type="button"
                class="flex w-full items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-2.5 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800"
                x-on:click="open = !open"
            >
                <span x-text="flag" class="text-base leading-none"></span>
                <span x-text="selected ? selected.phone_prefix : '+?'" class="text-xs text-zinc-500 dark:text-zinc-400" style="font-family: var(--font-mono);"></span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ml-auto size-3.5 text-zinc-400"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div
                x-show="open"
                x-cloak
                class="absolute left-0 top-full z-50 mt-1 max-h-56 w-64 overflow-auto rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-600 dark:bg-zinc-800"
            >
                <div class="sticky top-0 border-b border-zinc-200 bg-white p-1.5 dark:border-zinc-600 dark:bg-zinc-800">
                    <input
                        type="text"
                        x-model="search"
                        placeholder="Search..."
                        class="w-full rounded border-0 bg-zinc-50 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 dark:bg-zinc-700"
                    >
                </div>
                <template x-for="c in filtered" :key="c.iso2">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-2.5 py-1.5 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-700"
                        x-bind:class="{ 'bg-blue-50 dark:bg-blue-900/20': c.iso2 === code }"
                        x-on:click="select(c)"
                    >
                        <span x-text="[...c.iso2.toUpperCase()].map(ch => String.fromCodePoint(0x1F1E6 + ch.charCodeAt(0) - 65)).join('')" class="text-base leading-none"></span>
                        <span x-text="c.name" class="flex-1 truncate text-left"></span>
                        <span x-text="c.phone_prefix" class="text-xs text-zinc-400" style="font-family: var(--font-mono);"></span>
                    </button>
                </template>
                <div x-show="filtered.length === 0" class="px-2.5 py-2 text-sm text-zinc-400">No results</div>
            </div>
        </div>
        <div class="flex-1">
            <input
                type="tel"
                name="{{ $name }}"
                value="{{ $value }}"
                {{ $attributes->merge(['class' => 'w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-800']) }}
            >
        </div>
    </div>
</div>
