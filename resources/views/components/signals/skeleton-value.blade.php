@props(['width' => '2.5rem'])

{{--
    Shows a skeleton placeholder for an inline value on page load, then reveals
    the value once the view has painted. Wraps the shared x-signals.skeleton.
--}}
<span x-data="{ ready: false }" x-init="$nextTick(() => ready = true)" class="inline-block">
    <span x-show="!ready">
        <x-signals.skeleton type="rect" :width="$width" class="!h-4 inline-block align-middle" />
    </span>
    <span x-show="ready" x-cloak>{{ $slot }}</span>
</span>
