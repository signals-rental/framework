@props(['title' => 'Visible Columns'])

<div {{ $attributes->merge(['class' => 's-column-config']) }}>
    <div class="s-column-config-title">{{ $title }}</div>
    {{ $slot }}
</div>
