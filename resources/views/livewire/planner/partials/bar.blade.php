{{--
    A single job bar, positioned across the window track.

    Inputs:
      $row      — a decorated row from the component (left/width %, colour, bands,
                  status, customer flags, member/number).
      $top      — pixel offset from the track top.
      $height   — bar height in px.
      $withLabel (bool, default false) — render the number inside the bar (overlap
                  mode, where there is no separate row label column).

    Sub-bands (Delivery / In-Use / Collection) are positioned RELATIVE to the bar
    (their left/width are already 0–100 within the bar) and rendered as inset
    stripes. Customer Collecting / Customer Returning show as small badges.
--}}
@php
    $withLabel = $withLabel ?? false;
    $colour = $row['colour'];
    $tooltip = ($row['number'] ?? ('#'.$row['id']))
        .' · '.$row['state_label'].' · '.$row['status_label']
        .' · '.($row['starts_at']?->format('j M') ?? '')
        .' – '.($row['ends_at']?->format('j M') ?? '');
@endphp

<div class="absolute overflow-hidden rounded-sm border border-black/10 shadow-sm"
     style="left: {{ $row['left'] }}%; width: {{ $row['width'] }}%; top: {{ $top }}px; height: {{ $height }}px; background: {{ $colour }};"
     title="{{ $tooltip }}">

    {{-- Sub-bands as inset stripes along the bottom third of the bar --}}
    @foreach($row['bands'] as $band)
        <div wire:key="band-{{ $row['id'] }}-{{ $band['key'] }}"
             class="absolute bottom-0 h-1.5 {{ $band['key'] === 'in-use' ? 'opacity-90' : 'opacity-100' }}"
             style="left: {{ $band['left'] }}%; width: {{ $band['width'] }}%; background: {{ $band['key'] === 'in-use' ? 'rgba(255,255,255,0.55)' : 'rgba(0,0,0,0.35)' }};"
             title="{{ $band['label'] }}"></div>
    @endforeach

    {{-- Label + badges --}}
    <div class="flex h-full items-center gap-1.5 px-2 text-[10px] font-medium text-white">
        @if($withLabel)
            <a href="{{ route('opportunities.show', $row['id']) }}" wire:navigate
               class="truncate hover:underline">{{ $row['number'] ?? ('#'.$row['id']) }}@if($row['member']) — {{ $row['member'] }}@endif</a>
        @else
            <span class="truncate">{{ $row['subject'] }}</span>
        @endif

        @if($row['customer_collecting'])
            <span class="shrink-0 rounded-sm bg-white/25 px-1 py-px text-[9px] uppercase tracking-wide" title="{{ __('Customer collecting') }}">{{ __('Coll') }}</span>
        @endif
        @if($row['customer_returning'])
            <span class="shrink-0 rounded-sm bg-white/25 px-1 py-px text-[9px] uppercase tracking-wide" title="{{ __('Customer returning') }}">{{ __('Ret') }}</span>
        @endif
    </div>
</div>
