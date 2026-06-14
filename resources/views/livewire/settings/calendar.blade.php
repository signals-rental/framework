<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Calendar')] class extends Component {
    /**
     * The current user's permanent, signed iCal subscribe URL.
     */
    #[Computed]
    public function feedUrl(): string
    {
        return URL::signedRoute('calendar.feed.user', ['user' => Auth::id()]);
    }

    /**
     * The signed global iCal feed URL (shown to admins/owners only).
     */
    #[Computed]
    public function globalFeedUrl(): string
    {
        return URL::signedRoute('calendar.feed.global');
    }

    /**
     * Whether the current user may see the system-wide global feed.
     */
    #[Computed]
    public function canSeeGlobalFeed(): bool
    {
        $user = Auth::user();

        return $user->is_admin || $user->is_owner;
    }
}; ?>

<section class="w-full">
    <x-settings.layout heading="Calendar feed" subheading="Subscribe to your activities in any calendar app using your private iCal URL">
        <x-signals.form-section title="Your subscribe URL">
            <p class="mb-4 text-sm text-[var(--text-secondary)]">
                {{ __('This is a private subscription URL for your own calendar. Anyone with this link can view your activities, so keep it secret and only share it with apps you trust.') }}
            </p>

            <div class="flex items-center gap-2">
                <input
                    type="text"
                    readonly
                    value="{{ $this->feedUrl }}"
                    class="s-input flex-1 font-mono text-sm"
                    aria-label="{{ __('Calendar subscribe URL') }}"
                    onfocus="this.select()"
                />
                <x-signals.copy-btn :text="$this->feedUrl" class="shrink-0" />
            </div>

            <div class="mt-4 flex items-center gap-2">
                <a href="{{ $this->feedUrl }}" download="signals-calendar.ics" class="s-btn s-btn-sm">
                    <flux:icon.arrow-down-tray class="!size-[15px]" />
                    {{ __('Download .ics') }}
                </a>
            </div>
        </x-signals.form-section>

        <div class="mt-8"></div>

        <x-signals.form-section title="How to subscribe">
            <p class="mb-4 text-sm text-[var(--text-secondary)]">
                {{ __('Add the subscribe URL above to your calendar app to keep your activities in sync. It updates automatically.') }}
            </p>

            <ul class="space-y-3 text-sm text-[var(--text-secondary)]">
                <li>
                    <span class="font-medium text-[var(--text-primary)]">{{ __('Google Calendar') }}</span> —
                    {{ __('Other calendars → Add by URL → paste the subscribe URL.') }}
                </li>
                <li>
                    <span class="font-medium text-[var(--text-primary)]">{{ __('Apple Calendar') }}</span> —
                    {{ __('File → New Calendar Subscription → paste the subscribe URL.') }}
                </li>
                <li>
                    <span class="font-medium text-[var(--text-primary)]">{{ __('Outlook') }}</span> —
                    {{ __('Add calendar → Subscribe from web → paste the subscribe URL.') }}
                </li>
            </ul>
        </x-signals.form-section>

        @if ($this->canSeeGlobalFeed)
            <div class="mt-8"></div>

            <x-signals.form-section title="Global feed (admins)">
                <p class="mb-4 text-sm text-[var(--text-secondary)]">
                    {{ __('This feed contains every activity across all users. It is also a private, signed URL — share it only with people who should see the whole calendar.') }}
                </p>

                <div class="flex items-center gap-2">
                    <input
                        type="text"
                        readonly
                        value="{{ $this->globalFeedUrl }}"
                        class="s-input flex-1 font-mono text-sm"
                        aria-label="{{ __('Global calendar subscribe URL') }}"
                        onfocus="this.select()"
                    />
                    <x-signals.copy-btn :text="$this->globalFeedUrl" class="shrink-0" />
                </div>

                <div class="mt-4 flex items-center gap-2">
                    <a href="{{ $this->globalFeedUrl }}" download="signals-calendar-global.ics" class="s-btn s-btn-sm">
                        <flux:icon.arrow-down-tray class="!size-[15px]" />
                        {{ __('Download .ics') }}
                    </a>
                </div>
            </x-signals.form-section>
        @endif
    </x-settings.layout>
</section>
