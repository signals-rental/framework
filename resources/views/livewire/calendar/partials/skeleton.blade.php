{{--
    Calendar-shaped loading placeholder. Generic across day/week/month: a row of
    seven column-header blocks above a grid of shimmer blocks filling the area.
    Built from the shared <x-signals.skeleton> / s-skeleton shimmer primitive so it
    inherits the app's loading animation and dark-mode tokens.
--}}
<div class="s-cal flex-1 min-h-0" aria-hidden="true" wire:key="cal-skeleton">
    {{-- Column headers --}}
    <div class="flex" style="border-bottom: 1px solid var(--card-border);">
        <div style="flex-shrink: 0; width: 52px;"></div>
        @for($i = 0; $i < 7; $i++)
            <div class="flex-1 flex items-center justify-center" style="height: 56px; min-width: 96px; border-right: 1px solid var(--card-border);" wire:key="cal-skeleton-head-{{ $i }}">
                <x-signals.skeleton type="rect" width="60%" class="!h-4" />
            </div>
        @endfor
    </div>

    {{-- Grid body --}}
    <div class="flex flex-1 min-h-0">
        <div style="flex-shrink: 0; width: 52px; border-right: 1px solid var(--card-border);"></div>
        @for($i = 0; $i < 7; $i++)
            <div class="flex-1 flex flex-col gap-2 p-2" style="min-width: 96px; border-right: 1px solid var(--card-border);" wire:key="cal-skeleton-col-{{ $i }}">
                @for($j = 0; $j < 5; $j++)
                    <x-signals.skeleton type="rect" width="100%" class="!h-10" />
                @endfor
            </div>
        @endfor
    </div>
</div>
