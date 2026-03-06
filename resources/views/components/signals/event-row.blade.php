@props(['name' => null, 'actor' => null, 'time' => null, 'border' => 'create'])

<div {{ $attributes->merge(['class' => 's-es-event-row']) }}>
    <div class="s-es-event-border s-es-border-{{ $border }}"></div>
    <div class="s-es-event-body">
        <div class="s-es-event-top">
            @if($name)<span class="s-es-event-name">{{ $name }}</span>@endif
            @if($actor)<span class="s-es-event-actor">{{ $actor }}</span>@endif
            @if($time)<span class="s-es-event-time">{{ $time }}</span>@endif
        </div>
        @isset($payload)<div class="s-es-event-payload">{{ $payload }}</div>@endisset
    </div>
</div>
