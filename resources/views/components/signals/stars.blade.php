@props(['rating' => 0, 'max' => 5])

<div {{ $attributes->merge(['class' => 's-stars']) }}>
    @for($i = 1; $i <= $max; $i++)
        @if($i <= $rating)
            <svg class="s-star s-star-filled" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        @else
            <svg class="s-star s-star-empty" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        @endif
    @endfor
</div>
