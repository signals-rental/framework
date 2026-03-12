{{ $item->emails->firstWhere('is_primary', true)?->address ?? $item->emails->first()?->address ?? '' }}
