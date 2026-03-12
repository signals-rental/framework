{{ $item->phones->firstWhere('is_primary', true)?->number ?? $item->phones->first()?->number ?? '' }}
