@props(['label' => null])

{{--
    Selected-value display for a searchable autocomplete/dropdown.

    Matches the Flux text input (40px height, border, shadow, 14px text) so the
    field neither resizes nor changes case when a value is chosen. Corners stay
    sharp per the brand's global border-radius:0 rule. Pass the clear/remove
    control as the default slot.
--}}
<div {{ $attributes->merge(['class' => 'flex w-full items-center gap-2 border border-zinc-200 bg-white px-3 shadow-xs dark:border-white/10 dark:bg-white/10']) }} style="height: 40px;">
    <span class="flex-1 truncate text-sm text-zinc-700 dark:text-zinc-300">{{ $label }}</span>
    {{ $slot }}
</div>
