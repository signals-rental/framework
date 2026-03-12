@props(['title' => null])

@php($pageTitle = $title ?? null)

<x-layouts.app.header :title="$pageTitle">
    {{ $slot }}
</x-layouts.app.header>
