@props(['model', 'size' => 44, 'linkToFull' => true])

@php
    $words = preg_split('/\s+/', trim($model->name ?? ''));
    $initials = mb_strtoupper(
        mb_substr($words[0] ?? '', 0, 1) . mb_substr($words[1] ?? '', 0, 1)
    );

    $iconSrc = null;
    $iconFullSrc = null;
    $fileService = app(\App\Services\FileService::class);

    if ($model->icon_thumb_url ?? null) {
        try {
            $iconSrc = $fileService->signedUrl($model->icon_thumb_url);
        } catch (\Throwable) {
            // Fall back to initials
        }
    }
    if ($linkToFull && ($model->icon_url ?? null)) {
        try {
            $iconFullSrc = $fileService->signedUrl($model->icon_url);
        } catch (\Throwable) {
            // Fall back to thumb URL
        }
    }

    $fontSize = intval($size * 0.32);
@endphp

<div {{ $attributes->class(['flex items-center justify-center overflow-hidden rounded-lg border border-[var(--card-border)] bg-white shadow-sm'])->merge(['style' => "width: {$size}px; height: {$size}px; flex-shrink: 0;"]) }}>
    @if($iconSrc)
        @if($linkToFull)
            <a href="{{ $iconFullSrc ?? $iconSrc }}" target="_blank" class="block">
                <img src="{{ $iconSrc }}" alt="{{ $model->name }}" class="size-full object-cover" />
            </a>
        @else
            <img src="{{ $iconSrc }}" alt="{{ $model->name }}" class="size-full object-cover" />
        @endif
    @else
        <span class="font-bold text-[var(--text-muted)]" style="font-family: var(--font-display); font-size: {{ $fontSize }}px;">{{ $initials }}</span>
    @endif
</div>
