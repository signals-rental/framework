<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new #[Layout('components.layouts.app')] class extends Component {
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

        $storesSeeded = Schema::hasTable('stores') && \App\Models\Store::query()->exists();

        $demoNames = ['London Warehouse', 'Manchester Depot', 'Edinburgh Office'];
        $demoSeeded = Schema::hasTable('stores') && \App\Models\Store::query()->whereIn('name', $demoNames)->count() === count($demoNames);

        $this->seeders = [
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
                'description' => 'Creates demo stores for testing and evaluation.',
                'items' => [
                    'London Warehouse',
                    'Manchester Depot',
                    'Edinburgh Office',
                ],
                'seeded' => $demoSeeded,
                'isDefault' => false,
            ],
        ];
    }
}; ?>

<section class="w-full">
    <x-admin.layout title="Database Seeders" description="View seeder status and populate the database with default or demo data.">
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
                <div wire:key="seeder-{{ $key }}" class="s-card">
                    <div class="s-card-body flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="text-sm font-medium text-zinc-900 dark:text-white">{{ $seeder['name'] }}</h3>
                                @if($seeder['seeded'])
                                    <span class="s-status s-status-green">Seeded</span>
                                @else
                                    <span class="s-status s-status-zinc">Not Seeded</span>
                                @endif
                            </div>
                            <p class="text-sm text-zinc-500 mb-2">{{ $seeder['description'] }}</p>
                            <ul class="text-xs text-zinc-400 space-y-0.5 list-disc list-inside">
                                @foreach($seeder['items'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                        @unless($seeder['seeded'])
                            <flux:button size="sm" wire:click="seed('{{ $key }}')" wire:confirm="Run {{ $seeder['name'] }}?">
                                Run
                            </flux:button>
                        @endunless
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
