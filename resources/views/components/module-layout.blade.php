@props(['heading' => '', 'subheading' => ''])

<div class="flex items-start max-md:flex-col">
    @if(isset($navigation))
        <div class="mr-10 w-full pb-4 md:w-[220px]">
            {{ $navigation }}
        </div>

        <flux:separator class="md:hidden" />
    @endif

    <div class="flex-1 self-stretch max-md:pt-6">
        @if($heading)
            <flux:heading size="xl" level="1">{{ $heading }}</flux:heading>
        @endif
        @if($subheading)
            <flux:subheading>{{ $subheading }}</flux:subheading>
        @endif

        @if($heading || $subheading)
            <div class="mt-5">
                {{ $slot }}
            </div>
        @else
            {{ $slot }}
        @endif
    </div>
</div>
