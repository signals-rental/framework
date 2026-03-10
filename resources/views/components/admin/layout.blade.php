@props(['group' => 'setup', 'title' => '', 'description' => ''])

<div class="s-admin-layout">
    <x-admin.sidebar :group="$group" />

    <div class="s-admin-main">
        <div class="s-admin-section-header">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="s-admin-section-title">{{ $title }}</h1>
                    @if($description)
                        <p class="s-admin-section-desc">{{ $description }}</p>
                    @endif
                </div>
                @isset($actions)
                    <div>{{ $actions }}</div>
                @endisset
            </div>
        </div>

        <div class="space-y-6">
            {{ $slot }}
        </div>
    </div>
</div>
