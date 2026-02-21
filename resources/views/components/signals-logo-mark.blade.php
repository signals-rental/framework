@props(['size' => 'md', 'product' => 'green'])

@php
$sizes = [
    'sm' => 'text-xs px-2.5 py-1 tracking-[0.08em]',
    'md' => 'text-base px-4 py-1.5 tracking-[0.08em]',
    'lg' => 'text-2xl px-5 py-2 tracking-[0.08em]',
    'xl' => 'text-[2.5rem] px-7 py-3 tracking-[0.12em]',
];
$accents = [
    'sm' => 'w-[5px] h-[5px]',
    'md' => 'w-2 h-2',
    'lg' => 'w-2.5 h-2.5',
    'xl' => 'w-3.5 h-3.5',
];
$colors = [
    'blue' => 'bg-[#2563eb]',
    'green' => 'bg-[#059669]',
    'sky' => 'bg-[#0ea5e9]',
    'cyan' => 'bg-[#0891b2]',
    'amber' => 'bg-[#d97706]',
    'violet' => 'bg-[#7c3aed]',
    'rose' => 'bg-[#e11d48]',
    'slate' => 'bg-[#475569]',
];
@endphp

<span {{ $attributes->merge(['class' => 'relative inline-block font-[var(--font-display)] font-bold uppercase border-2 border-current ' . $sizes[$size]]) }}>
    SIGNALS
    <span class="absolute -top-[2px] -right-[2px] {{ $accents[$size] }} {{ $colors[$product] }}"></span>
</span>
