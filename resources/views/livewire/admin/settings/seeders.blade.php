<?php

use App\Models\Country;
use App\Models\EmailTemplate;
use App\Models\ListName;
use App\Models\NotificationType;
use App\Models\OrganisationTaxClass;
use App\Models\ProductTaxClass;
use App\Models\TaxRate;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new #[Layout('components.layouts.app')] #[Title('Seeders')] class extends Component {
    /** @var array<string, array{name: string, class: string, description: string, items: list<string>, seeded: bool, isDefault: bool}> */
    public array $seeders = [];

    public function mount(): void
    {
        $this->loadSeederStatus();
    }

    public function seed(string $key): void
    {
        $seeder = $this->seeders[$key] ?? null;

        if (! $seeder) {
            $this->addError('seeder', "Unknown seeder: {$key}");

            return;
        }

        try {
            Artisan::call('db:seed', [
                '--class' => $seeder['class'],
                '--force' => true,
            ]);

            $this->loadSeederStatus();
            $this->dispatch('seeder-completed');
        } catch (\Exception $e) {
            $this->addError('seeder', "Seeder failed: {$e->getMessage()}");
        }
    }

    public function seedAll(): void
    {
        try {
            Artisan::call('db:seed', ['--force' => true]);
            $this->loadSeederStatus();
            $this->dispatch('seeder-completed');
        } catch (\Exception $e) {
            $this->addError('seeder', "Seeder failed: {$e->getMessage()}");
        }
    }

    private function loadSeederStatus(): void
    {
        $permissionsSeeded = Schema::hasTable('permissions') && Permission::query()->exists();

        $systemRoles = ['Admin', 'Manager', 'Operator', 'Viewer'];
        $rolesSeeded = Schema::hasTable('roles') && Role::query()->whereIn('name', $systemRoles)->count() === count($systemRoles);

        $emailTemplatesSeeded = Schema::hasTable('email_templates') && EmailTemplate::query()->exists();
        $notificationTypesSeeded = Schema::hasTable('notification_types') && NotificationType::query()->exists();

        $countriesSeeded = Schema::hasTable('countries') && Country::query()->exists();
        $listsSeeded = Schema::hasTable('list_names') && ListName::query()->where('is_system', true)->exists();
        $taxClassesSeeded = Schema::hasTable('product_tax_classes') && ProductTaxClass::query()->exists()
            && Schema::hasTable('organisation_tax_classes') && OrganisationTaxClass::query()->exists();

        $taxRatesSeeded = Schema::hasTable('tax_rates') && TaxRate::query()->exists();

        $storesSeeded = Schema::hasTable('stores') && \App\Models\Store::query()->exists();

        $demoNames = ['London Warehouse', 'Manchester Depot', 'Edinburgh Office'];
        $demoStoresSeeded = Schema::hasTable('stores') && \App\Models\Store::query()->whereIn('name', $demoNames)->count() === count($demoNames);
        $demoMembersSeeded = Schema::hasTable('members') && \App\Models\Member::query()->count() >= 5000;

        $this->seeders = [
            'countries' => [
                'name' => 'CountrySeeder',
                'class' => 'Database\\Seeders\\CountrySeeder',
                'description' => 'Populates the countries table from the reference data file.',
                'items' => [
                    'All ISO 3166-1 countries with currency codes, phone prefixes, and default timezones',
                ],
                'seeded' => $countriesSeeded,
                'isDefault' => true,
            ],
            'lists' => [
                'name' => 'ListOfValuesSeeder',
                'class' => 'Database\\Seeders\\ListOfValuesSeeder',
                'description' => 'Creates system lists used for contact detail type classification.',
                'items' => [
                    'Address Type (Billing, Shipping, Primary, Registered)',
                    'Email Type (Work, Personal, Billing, Support)',
                    'Phone Type (Work, Mobile, Home, Fax)',
                    'Link Type (Website, LinkedIn, Facebook, Instagram, X, YouTube)',
                    'Relationship Type (Employee, Director, Contractor, Agent)',
                ],
                'seeded' => $listsSeeded,
                'isDefault' => true,
            ],
            'tax_classes' => [
                'name' => 'TaxClassSeeder',
                'class' => 'Database\\Seeders\\TaxClassSeeder',
                'description' => 'Creates default product and organisation tax classes.',
                'items' => [
                    'Organisation tax class: Standard',
                    'Product tax classes: Standard, Exempt',
                ],
                'seeded' => $taxClassesSeeded,
                'isDefault' => true,
            ],
            'tax_rates' => [
                'name' => 'TaxRateSeeder',
                'class' => 'Database\\Seeders\\TaxRateSeeder',
                'description' => 'Creates default UK tax rates.',
                'items' => [
                    'Standard — 20%',
                    'Reduced — 5%',
                    'Zero — 0%',
                ],
                'seeded' => $taxRatesSeeded,
                'isDefault' => true,
            ],
            'permissions' => [
                'name' => 'PermissionSeeder',
                'class' => 'Database\\Seeders\\PermissionSeeder',
                'description' => 'Creates all system permissions used for role-based access control.',
                'items' => [
                    'Settings permissions (view, manage)',
                    'User management permissions (view, invite, edit, deactivate, activate, reset-password)',
                    'Role permissions (view, manage)',
                    'Resource permissions (members, opportunities, invoices, products, stock)',
                    'System permissions (action-log, custom-fields, static-data, webhooks)',
                ],
                'seeded' => $permissionsSeeded,
                'isDefault' => true,
            ],
            'roles' => [
                'name' => 'RoleSeeder',
                'class' => 'Database\\Seeders\\RoleSeeder',
                'description' => 'Creates the four system roles with their default permission sets.',
                'items' => [
                    'Admin — all permissions',
                    'Manager — resource permissions (no settings/users/roles)',
                    'Operator — core operational permissions (opportunities, invoicing, stock)',
                    'Viewer — read-only access to all resources',
                ],
                'seeded' => $rolesSeeded,
                'isDefault' => true,
            ],
            'email_templates' => [
                'name' => 'EmailTemplateSeeder',
                'class' => 'Database\\Seeders\\EmailTemplateSeeder',
                'description' => 'Creates default email templates for system notifications.',
                'items' => [
                    'User Invited — invitation email sent to new users',
                    'Password Reset — password reset notification',
                    'Test Email — email delivery verification',
                ],
                'seeded' => $emailTemplatesSeeded,
                'isDefault' => true,
            ],
            'notification_types' => [
                'name' => 'NotificationTypeSeeder',
                'class' => 'Database\\Seeders\\NotificationTypeSeeder',
                'description' => 'Creates core notification types with default channel configurations.',
                'items' => [
                    'User notifications (invited, deactivated, reactivated)',
                    'System notifications (password reset, test email)',
                ],
                'seeded' => $notificationTypesSeeded,
                'isDefault' => true,
            ],
            'stores' => [
                'name' => 'StoreSeeder',
                'class' => 'Database\\Seeders\\StoreSeeder',
                'description' => 'Creates a default store if none exist.',
                'items' => [
                    'Main Warehouse (default store)',
                ],
                'seeded' => $storesSeeded,
                'isDefault' => false,
            ],
            'demo' => [
                'name' => 'DemoDataSeeder',
                'class' => 'Database\\Seeders\\DemoDataSeeder',
                'description' => 'Creates demo stores and populates the CRM with members, contact details, and relationships.',
                'items' => [
                    '3 demo stores (London, Manchester, Edinburgh)',
                    '2,000 organisations with email & phone each',
                    '500 venues with email & phone each',
                    '3,000 contacts with email & phone each',
                    'Relationships linking contacts to organisations and venues',
                ],
                'seeded' => $demoStoresSeeded && $demoMembersSeeded,
                'isDefault' => false,
            ],
        ];
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="system" title="Database Seeders" description="View seeder status and populate the database with default or demo data.">
        @php
            $anyDefaultSeeded = collect($seeders)->where('isDefault', true)->contains('seeded', true);
        @endphp

        <div class="mb-6 flex items-center justify-between">
            <p class="text-sm text-zinc-500">
                The default <code class="text-xs s-badge">php artisan db:seed</code> command runs PermissionSeeder, RoleSeeder, and creates a test user.
            </p>
            @unless($anyDefaultSeeded)
                <flux:button wire:click="seedAll" wire:confirm="Run all default seeders (PermissionSeeder + RoleSeeder + test user)?">
                    Run Default Seeders
                </flux:button>
            @endunless
        </div>

        <div class="space-y-4">
            @foreach($seeders as $key => $seeder)
                <div wire:key="seeder-{{ $key }}" class="s-card" x-data="{ open: {{ $seeder['seeded'] ? 'false' : 'true' }} }">
                    <div class="s-card-body flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <button type="button" @click="open = !open" class="flex items-center gap-2 mb-1 text-left w-full">
                                <flux:icon.chevron-right class="size-4 text-zinc-400 transition-transform" ::class="open && 'rotate-90'" />
                                <h3 class="text-sm font-medium text-zinc-900 dark:text-white">{{ $seeder['name'] }}</h3>
                                @if($seeder['seeded'])
                                    <span class="s-status s-status-green">Seeded</span>
                                @else
                                    <span class="s-status s-status-zinc">Not Seeded</span>
                                @endif
                            </button>
                            <div x-show="open" x-collapse>
                                <p class="text-sm text-zinc-500 mb-2 ml-6">{{ $seeder['description'] }}</p>
                                <ul class="text-xs text-zinc-400 space-y-0.5 list-disc list-inside ml-6">
                                    @foreach($seeder['items'] as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        <flux:button size="sm" variant="{{ $seeder['seeded'] ? 'ghost' : 'filled' }}" wire:click="seed('{{ $key }}')" wire:confirm="Run {{ $seeder['name'] }}?">
                            {{ $seeder['seeded'] ? 'Re-run' : 'Run' }}
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>

        @error('seeder') <p class="text-sm text-red-600 mt-4">{{ $message }}</p> @enderror

        <x-action-message class="mt-4" on="seeder-completed">
            Seeder completed successfully.
        </x-action-message>
    </x-admin.layout>
</section>
