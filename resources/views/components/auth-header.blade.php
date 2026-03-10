@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-2">
    <h1 class="s-auth-heading">{{ $title }}</h1>
    <p class="s-auth-description">{{ $description }}</p>
</div>
