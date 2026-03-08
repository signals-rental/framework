@props([
    'multiple' => false,
])

<div
    {{ $attributes->merge(['class' => 's-accordion']) }}
    x-data="{ openItems: [], multiple: @js($multiple), toggle(id) { if (this.multiple) { const idx = this.openItems.indexOf(id); idx === -1 ? this.openItems.push(id) : this.openItems.splice(idx, 1); } else { this.openItems = this.openItems.includes(id) ? [] : [id]; } }, isOpen(id) { return this.openItems.includes(id); } }"
>
    {{ $slot }}
</div>
