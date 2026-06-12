@props(['serialised' => false, 'label' => null])
@php($text = $label ?? ($serialised ? 'Serialised' : 'Bulk'))
<span {{ $attributes->merge(['class' => 's-badge ' . ($serialised ? 's-badge-violet' : 's-badge-cyan')]) }} style="display: inline-flex; align-items: center; gap: 4px;">
    @if($serialised)
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/></svg>
    @else
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M12 2 2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
    @endif
    {{ $text }}
</span>
