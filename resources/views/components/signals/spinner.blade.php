@props(['size' => 'sm'])

{{--
    Inline loading spinner. Renders the standard s-spinner SVG (CSS animation in
    components.css §73) at one of the size tokens (xs|sm|md|lg). Any extra
    attributes (e.g. wire:loading wire:target=…, style, class) pass through, so
    it can be dropped straight into a wire:loading region.
--}}
<svg {{ $attributes->merge(['class' => 's-spinner s-spinner-'.$size]) }}
     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
    <path d="M12 2v4m0 12v4m-7.07-3.93l2.83-2.83m8.48-8.48l2.83-2.83M2 12h4m12 0h4m-3.93 7.07l-2.83-2.83M7.76 7.76L4.93 4.93"/>
</svg>
