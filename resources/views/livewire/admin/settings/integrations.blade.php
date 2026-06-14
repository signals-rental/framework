<?php

use App\Services\Auth\SsoService;
use App\Services\SettingsRegistry;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Integrations')] class extends Component {
    public string $what3wordsApiKey = '';
    public string $googleMapsApiKey = '';

    public bool $ssoGoogleEnabled = false;
    public string $ssoGoogleClientId = '';

    /**
     * Write-only: never populated from storage. A blank value on save keeps the
     * stored secret; a non-empty value rotates it. Decrypted secrets are never
     * round-tripped to the browser.
     */
    public string $ssoGoogleClientSecret = '';

    public bool $ssoMicrosoftEnabled = false;
    public string $ssoMicrosoftClientId = '';

    /** Write-only — see {@see $ssoGoogleClientSecret}. */
    public string $ssoMicrosoftClientSecret = '';

    /** Comma/space/newline-separated owned email domains for the SSO allow-list. */
    public string $ssoAllowedEmailDomains = '';

    public function mount(): void
    {
        $group = settings()->group('integrations');

        $this->what3wordsApiKey = (string) $group['what3words_api_key'];
        $this->googleMapsApiKey = (string) $group['google_maps_api_key'];

        $this->ssoGoogleEnabled = (bool) settings('sso.google_enabled');
        $this->ssoGoogleClientId = (string) settings('sso.google_client_id');
        $this->ssoMicrosoftEnabled = (bool) settings('sso.microsoft_enabled');
        $this->ssoMicrosoftClientId = (string) settings('sso.microsoft_client_id');

        // Client secrets are write-only: leave the inputs blank and never expose
        // the stored (decrypted) values to the browser.

        $this->ssoAllowedEmailDomains = implode("\n", $this->storedAllowedEmailDomains());
    }

    /**
     * Whether the application is running on Signals Cloud.
     *
     * On Cloud, credentials are managed centrally (env), so the per-provider key
     * fields are hidden and never persisted — only the enable toggles and the
     * allow-list are editable. Cached for the request so both save() and the view
     * resolve it once.
     */
    #[Computed]
    public function isCloud(): bool
    {
        return app(SsoService::class)->isCloud();
    }

    /**
     * Per-provider metadata driving the SSO provider blocks in the view.
     *
     * Each entry carries the provider slug, the credential-field label base and the
     * enable-toggle label, plus the component property names for its enable toggle /
     * client id / client secret inputs, whether a secret is already stored (the
     * write-only "leave blank to keep" indicator) and its self-hosted callback URL.
     *
     * @return list<array{slug: string, label: string, enableLabel: string, enabledProp: string, clientIdProp: string, clientSecretProp: string, secretConfigured: bool, callbackUrl: string}>
     */
    #[Computed]
    public function ssoProviders(): array
    {
        return [
            [
                'slug' => 'google',
                'label' => 'Google',
                'enableLabel' => 'Google',
                'enabledProp' => 'ssoGoogleEnabled',
                'clientIdProp' => 'ssoGoogleClientId',
                'clientSecretProp' => 'ssoGoogleClientSecret',
                'secretConfigured' => filled(settings('sso.google_client_secret')),
                'callbackUrl' => route('sso.callback', ['provider' => 'google']),
            ],
            [
                'slug' => 'microsoft',
                'label' => 'Microsoft',
                'enableLabel' => 'Microsoft 365',
                'enabledProp' => 'ssoMicrosoftEnabled',
                'clientIdProp' => 'ssoMicrosoftClientId',
                'clientSecretProp' => 'ssoMicrosoftClientSecret',
                'secretConfigured' => filled(settings('sso.microsoft_client_secret')),
                'callbackUrl' => route('sso.callback', ['provider' => 'microsoft']),
            ],
        ];
    }

    /**
     * Read the stored allow-list as a clean list of domain strings.
     *
     * @return list<string>
     */
    protected function storedAllowedEmailDomains(): array
    {
        $stored = settings('sso.allowed_email_domains');

        if (! is_array($stored)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($domain): string => is_string($domain) ? trim($domain) : '',
            $stored,
        ), fn (string $domain): bool => $domain !== ''));
    }

    /**
     * Normalise free-text domain input into a lowercased, de-duplicated list.
     *
     * Accepts comma, whitespace, and newline separators.
     *
     * @return list<string>
     */
    protected function normaliseAllowedEmailDomains(string $input): array
    {
        $parts = preg_split('/[\s,]+/', $input) ?: [];
        $domains = [];

        foreach ($parts as $part) {
            $normalised = strtolower(trim($part));

            if ($normalised !== '') {
                $domains[] = $normalised;
            }
        }

        return array_values(array_unique($domains));
    }

    /**
     * Validate and persist the integration + SSO settings.
     *
     * Credential fields (client id/secret) are only validated and persisted on
     * self-hosted installs; client secrets are write-only (a blank input keeps the
     * stored value). The allow-list is policy and is persisted on Cloud too.
     */
    public function save(): void
    {
        $registry = app(SettingsRegistry::class);
        $rules = $registry->rules('integrations');
        $types = $registry->types('integrations');
        $isCloud = $this->isCloud();

        $rulesToValidate = [
            'what3wordsApiKey' => $rules['what3words_api_key'],
            'googleMapsApiKey' => $rules['google_maps_api_key'],
            'ssoGoogleEnabled' => $rules['sso.google_enabled'],
            'ssoMicrosoftEnabled' => $rules['sso.microsoft_enabled'],
        ];

        // Credential fields are only editable on self-hosted installs.
        if (! $isCloud) {
            $rulesToValidate['ssoGoogleClientId'] = $rules['sso.google_client_id'];
            $rulesToValidate['ssoGoogleClientSecret'] = $rules['sso.google_client_secret'];
            $rulesToValidate['ssoMicrosoftClientId'] = $rules['sso.microsoft_client_id'];
            $rulesToValidate['ssoMicrosoftClientSecret'] = $rules['sso.microsoft_client_secret'];
        }

        $validated = $this->validate($rulesToValidate);

        $toPersist = [
            'integrations.what3words_api_key' => ['value' => $validated['what3wordsApiKey'], 'type' => $types['what3words_api_key'] ?? 'string'],
            'integrations.google_maps_api_key' => ['value' => $validated['googleMapsApiKey'], 'type' => $types['google_maps_api_key'] ?? 'string'],
            'sso.google_enabled' => ['value' => $validated['ssoGoogleEnabled'], 'type' => $types['sso.google_enabled']],
            'sso.microsoft_enabled' => ['value' => $validated['ssoMicrosoftEnabled'], 'type' => $types['sso.microsoft_enabled']],
            // The allow-list is policy, not a credential — persisted on cloud and self-hosted alike.
            'sso.allowed_email_domains' => ['value' => $this->normaliseAllowedEmailDomains($this->ssoAllowedEmailDomains), 'type' => $types['sso.allowed_email_domains']],
        ];

        if (! $isCloud) {
            $toPersist['sso.google_client_id'] = ['value' => $validated['ssoGoogleClientId'], 'type' => $types['sso.google_client_id']];
            $toPersist['sso.microsoft_client_id'] = ['value' => $validated['ssoMicrosoftClientId'], 'type' => $types['sso.microsoft_client_id']];

            // Client secrets are write-only: only persist when a non-empty value was
            // entered. A blank input keeps the existing stored secret untouched.
            if ($validated['ssoGoogleClientSecret'] !== '') {
                $toPersist['sso.google_client_secret'] = ['value' => $validated['ssoGoogleClientSecret'], 'type' => $types['sso.google_client_secret']];
            }

            if ($validated['ssoMicrosoftClientSecret'] !== '') {
                $toPersist['sso.microsoft_client_secret'] = ['value' => $validated['ssoMicrosoftClientSecret'], 'type' => $types['sso.microsoft_client_secret']];
            }
        }

        settings()->setMany($toPersist);

        // Reset write-only inputs so a stale plaintext secret is never held in component state.
        $this->ssoGoogleClientSecret = '';
        $this->ssoMicrosoftClientSecret = '';
        $this->ssoAllowedEmailDomains = implode("\n", $this->storedAllowedEmailDomains());

        $this->dispatch('integration-settings-saved');
    }

    /**
     * Expose view data. Cloud flag and per-provider SSO metadata are resolved via
     * the `isCloud` / `ssoProviders` computed properties referenced in the markup.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [];
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="preferences" title="Integrations" description="Configure API keys for third-party services.">
        <form wire:submit="save" class="space-y-6">
            <x-signals.form-section title="Geocoding">
                <div class="space-y-4">
                    <flux:input wire:model="what3wordsApiKey" label="what3words API Key" type="password" placeholder="Enter your what3words API key" description="Used for precise address geocoding. Get a key at developer.what3words.com" />
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Maps">
                <div class="space-y-4">
                    <flux:input wire:model="googleMapsApiKey" label="Google Maps API Key" type="password" placeholder="Enter your Google Maps API key" description="Used for address autocomplete and map display." />
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Single Sign-On">
                <div class="space-y-6">
                    @if($this->isCloud)
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            Single sign-on credentials are managed by Signals Cloud. Use the toggles below to control which providers your users can sign in with.
                        </p>
                    @endif

                    @foreach($this->ssoProviders as $provider)
                        <div class="space-y-4" wire:key="sso-{{ $provider['slug'] }}">
                            <label class="flex items-center gap-2 cursor-pointer" x-data="{ checked: @js($this->{$provider['enabledProp']}) }">
                                <input type="checkbox" wire:model="{{ $provider['enabledProp'] }}" class="hidden" x-on:change="checked = $el.checked" />
                                <x-signals.checkbox x-bind:class="checked && 'checked'" />
                                <span class="text-sm font-medium">Enable {{ $provider['enableLabel'] }} sign-in</span>
                            </label>

                            @unless($this->isCloud)
                                <div class="space-y-4 pl-7">
                                    <flux:input wire:model="{{ $provider['clientIdProp'] }}" label="{{ $provider['label'] }} Client ID" placeholder="Enter your {{ $provider['label'] }} OAuth client ID" />
                                    <flux:input wire:model="{{ $provider['clientSecretProp'] }}" label="{{ $provider['label'] }} Client Secret" type="password"
                                        placeholder="{{ $provider['secretConfigured'] ? 'Configured — leave blank to keep' : 'Enter your ' . $provider['label'] . ' OAuth client secret' }}"
                                        description="{{ $provider['secretConfigured'] ? 'A secret is already configured. Leave blank to keep it, or enter a new value to replace it.' : 'Stored encrypted and never shown again after saving.' }}" />
                                    <flux:input value="{{ $provider['callbackUrl'] }}" label="Redirect / Callback URL" readonly copyable description="Add this URL to your {{ $provider['label'] }} OAuth client's authorised redirect URIs." />
                                </div>
                            @endunless
                        </div>
                    @endforeach

                    {{-- Allowed email domains (policy — shown on cloud and self-hosted) --}}
                    <div class="space-y-3 border-t pt-6" style="border-color: var(--card-border);" wire:key="sso-allowed-domains">
                        <flux:textarea wire:model="ssoAllowedEmailDomains" label="Allowed email domains"
                            placeholder="example.com&#10;example.co.uk" rows="3"
                            description="One per line (commas and spaces also work). Only users whose email domain is on this list may sign in or be auto-linked via SSO. Leave blank to allow any domain." />
                        <x-signals.alert type="warning" title="Recommended for Microsoft sign-in" class="text-sm">
                            Microsoft is configured with the multitenant <code>common</code> setting, so any work, school, or personal Microsoft account can complete the OAuth flow. Configuring an allow-list of <em>your owned domains</em> is strongly recommended to prevent cross-tenant account takeover. <strong>An empty allow-list permits any email domain.</strong>
                        </x-signals.alert>
                    </div>
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">Save Changes</flux:button>
                <x-action-message on="integration-settings-saved">Saved.</x-action-message>
            </div>
        </form>
    </x-admin.layout>
</section>
