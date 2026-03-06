@props(['placeholder' => 'Search...'])

<div {{ $attributes->merge(['class' => 's-search']) }}>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" placeholder="{{ $placeholder }}">
</div>
