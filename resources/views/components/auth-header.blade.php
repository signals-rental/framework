@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-2">
    <h1 class="signals-auth-heading">{{ $title }}</h1>
    <p class="signals-auth-description">{{ $description }}</p>
</div>
