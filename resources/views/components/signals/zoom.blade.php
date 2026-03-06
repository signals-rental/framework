@props(['level' => '100%'])

<div {{ $attributes->merge(['class' => 's-zoom']) }}>
    <button class="s-zoom-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/></svg></button>
    <span class="s-zoom-level">{{ $level }}</span>
    <button class="s-zoom-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></button>
</div>
