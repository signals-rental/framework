@props(['checked' => false])

<div {{ $attributes->merge(['class' => 's-checkbox' . ($checked ? ' checked' : '')]) }}>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
</div>
