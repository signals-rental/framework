<?php

use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Profile')] class extends Component {
    public string $name = '';
    public string $email = '';

    public Member $member;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->member = $this->resolveMember($user);
    }

    /**
     * Resolve the authenticated user's linked member record, creating a
     * User-type member if one does not yet exist (legacy member_id gap).
     */
    private function resolveMember(User $user): Member
    {
        if ($user->member) {
            return $user->member;
        }

        $member = Member::create([
            'name' => $user->name,
            'membership_type' => MembershipType::User,
            'is_active' => $user->is_active,
        ]);

        $user->update(['member_id' => $member->id]);

        return $member;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id)
            ],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        // Keep the linked member record's name in sync. User-type members are
        // managed from this profile page, so the user's name is authoritative.
        $user->syncMemberName();

        // The user's name drives the header avatar initials (rendered in the
        // global chrome, outside this component). Force a full reload so the
        // header updates; the flashed status survives the redirect.
        session()->flash('status', 'Profile saved.');

        $this->redirect(route('settings.profile'), navigate: false);
    }

    /**
     * Handle the embedded IconUpload component reporting a profile-photo change.
     *
     * The header avatar is rendered in the global chrome and a Livewire morph
     * cannot update it, so we force a full reload. This listener lives on the
     * profile page only — the shared IconUpload component does not reload, so
     * member/product edit pages are unaffected.
     */
    #[On('icon-updated')]
    public function handleProfilePhotoUpdated(): void
    {
        $this->redirect(route('settings.profile'), navigate: false);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section class="w-full">
    <x-settings.layout heading="Profile" subheading="Update your name and email address">
        @if (session('status') && session('status') !== 'verification-link-sent')
            <x-signals.alert type="success" dismissible class="mb-6">{{ session('status') }}</x-signals.alert>
        @endif

        <x-signals.form-section title="Profile photo">
            <p class="mb-4 text-sm text-[var(--text-secondary)]">{{ __('Upload a photo to personalise your account.') }}</p>
            <livewire:components.icon-upload :model="$member" :key="'profile-photo-'.$member->id" />
        </x-signals.form-section>

        <div class="mt-8"></div>

        <x-signals.form-section title="Profile information">
            <form wire:submit="updateProfileInformation" class="space-y-6">
                <flux:input wire:model="name" label="{{ __('Name') }}" type="text" name="name" required autofocus autocomplete="name" />

                <div>
                    <flux:input wire:model="email" label="{{ __('Email') }}" type="email" name="email" required autocomplete="email" />

                    @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                        <div class="mt-2 space-y-1">
                            <p class="text-sm text-[var(--text-secondary)]">
                                {{ __('Your email address is unverified.') }}

                                <button
                                    wire:click.prevent="resendVerificationNotification"
                                    class="underline hover:text-[var(--text-primary)]"
                                >
                                    {{ __('Click here to re-send the verification email.') }}
                                </button>
                            </p>

                            @if (session('status') === 'verification-link-sent')
                                <p class="text-sm font-medium text-green-600">
                                    {{ __('A new verification link has been sent to your email address.') }}
                                </p>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </x-signals.form-section>

        <div class="mt-8">
            <livewire:settings.two-factor-authentication-form />
        </div>

        <livewire:settings.delete-user-form />
    </x-settings.layout>
</section>
