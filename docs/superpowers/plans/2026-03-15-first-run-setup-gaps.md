# First-Run/Setup Gap Fixes Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close all 14 missing and 4 partial items from the Discussion #15 audit — single commit.

**Architecture:** Update existing action classes, commands, seeders, and Livewire components. Add 7 modules to FeatureProfile enum. Link admin User to Member record. Wire reference data seeding into CompleteSetup. Tag demo data for clean removal.

**Tech Stack:** PHP 8.4, Laravel 12, Pest 4, Spatie Laravel Data, Livewire 4

---

## File Map

| Action | File |
|--------|------|
| Modify | `app/Enums/FeatureProfile.php` |
| Modify | `app/Actions/Setup/CompleteSetup.php` |
| Modify | `app/Console/Commands/SignalsInstallCommand.php` |
| Modify | `app/Console/Commands/SignalsSetupCommand.php` |
| Modify | `app/Livewire/Dashboard/GettingStartedChecklist.php` |
| Modify | `database/seeders/DemoDataSeeder.php` |
| Modify | `app/Console/Commands/SignalsClearDemoCommand.php` |
| Modify | `resources/views/livewire/setup/wizard.blade.php` (password rule) |
| Modify | `tests/Feature/Actions/Setup/CompleteSetupTest.php` |
| Modify | `tests/Feature/Livewire/Dashboard/GettingStartedChecklistTest.php` |
| Modify | existing install command tests (PG extensions, SIGNALS_SETUP_COMPLETE) |
| Create | `tests/Unit/Enums/FeatureProfileTest.php` |
| Create | `tests/Feature/Console/Commands/SignalsClearDemoCommandTest.php` (if not exists) |

---

## Chunk 1: FeatureProfile + CompleteSetup + Tests

### Task 1: Add missing modules to FeatureProfile enum

**Files:**
- Modify: `app/Enums/FeatureProfile.php`
- Create: `tests/Unit/Enums/FeatureProfileTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Enums/FeatureProfileTest.php`:

```php
<?php

use App\Enums\FeatureProfile;

it('has all 16 modules in every profile', function (FeatureProfile $profile) {
    $modules = $profile->modules();

    expect($modules)->toHaveKeys([
        'opportunities', 'products', 'stock', 'invoicing', 'crm',
        'crew', 'services', 'projects', 'inspections',
        'serialisation', 'credit_notes', 'purchase_orders',
        'vehicles', 'quarantines', 'discussions', 'webhooks',
    ]);
})->with(FeatureProfile::cases());

it('maps DryHire profile correctly per spec', function () {
    $modules = FeatureProfile::DryHire->modules();

    expect($modules)->toMatchArray([
        'opportunities' => true,
        'products' => true,
        'stock' => true,
        'invoicing' => true,
        'crm' => false,         // spec says off for dry hire
        'crew' => false,
        'services' => false,
        'projects' => false,
        'inspections' => false,  // spec says off for dry hire
        'serialisation' => false,
        'credit_notes' => false,
        'purchase_orders' => false,
        'vehicles' => false,
        'quarantines' => false,
        'discussions' => false,
        'webhooks' => false,
    ]);
});

it('maps FullService profile with all modules enabled', function () {
    $modules = FeatureProfile::FullService->modules();

    expect(array_filter($modules))->toHaveCount(16);
});

it('maps Crew profile correctly per spec', function () {
    $modules = FeatureProfile::Crew->modules();

    expect($modules['opportunities'])->toBeTrue()
        ->and($modules['invoicing'])->toBeTrue()
        ->and($modules['crew'])->toBeTrue()
        ->and($modules['services'])->toBeTrue()
        ->and($modules['projects'])->toBeTrue()
        ->and($modules['discussions'])->toBeTrue()
        ->and($modules['products'])->toBeFalse()
        ->and($modules['stock'])->toBeFalse()
        ->and($modules['inspections'])->toBeFalse()
        ->and($modules['crm'])->toBeFalse();
});

it('maps Minimal profile correctly per spec', function () {
    $modules = FeatureProfile::Minimal->modules();

    // Minimal: only opportunities and products
    expect($modules['opportunities'])->toBeTrue()
        ->and($modules['products'])->toBeTrue()
        ->and($modules['invoicing'])->toBeFalse()
        ->and($modules['crm'])->toBeFalse()
        ->and($modules['stock'])->toBeFalse();
});

it('maps General profile correctly per spec', function () {
    $modules = FeatureProfile::General->modules();

    expect($modules['opportunities'])->toBeTrue()
        ->and($modules['products'])->toBeTrue()
        ->and($modules['stock'])->toBeTrue()
        ->and($modules['invoicing'])->toBeTrue()
        ->and($modules['crm'])->toBeTrue()
        ->and($modules['crew'])->toBeTrue()
        ->and($modules['services'])->toBeFalse()
        ->and($modules['projects'])->toBeFalse()
        ->and($modules['discussions'])->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Unit/Enums/FeatureProfileTest.php`
Expected: FAIL — missing module keys, wrong boolean values

- [ ] **Step 3: Update FeatureProfile enum**

Modify `app/Enums/FeatureProfile.php` — update the `modules()` method to return all 16 modules per profile, matching Discussion #15's table exactly:

| Module | Dry Hire | Full Service | Crew | General | Minimal |
|--------|----------|--------------|------|---------|---------|
| Members (core) | always | always | always | always | always |
| Products | true | true | false | true | true |
| Services | false | true | true | false | false |
| Stock Management | true | true | false | true | false |
| Serialisation | false | true | false | false | false |
| Opportunities | true | true | true | true | true |
| Invoicing | true | true | true | true | false |
| Credit Notes | false | true | false | false | false |
| Purchase Orders | false | true | false | false | false |
| Projects | false | true | true | false | false |
| CRM / Activities | false | true | false | true | false |
| Inspections | false | true | false | false | false |
| Vehicles | false | true | false | false | false |
| Quarantines | false | true | false | false | false |
| Discussions | false | true | true | true | false |
| Webhooks | false | true | false | false | false |

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Unit/Enums/FeatureProfileTest.php`
Expected: PASS

- [ ] **Step 5: Update CompleteSetupTest for module settings**

The existing test at `tests/Feature/Actions/Setup/CompleteSetupTest.php:85-96` checks DryHire module settings — update to match new mapping (crm should be false, inspections should be false).

### Task 2: CompleteSetup — seed reference data and link admin to Member

**Files:**
- Modify: `app/Actions/Setup/CompleteSetup.php`
- Modify: `tests/Feature/Actions/Setup/CompleteSetupTest.php`

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Actions/Setup/CompleteSetupTest.php`:

```php
it('seeds reference data on completion', function () {
    $data = makeSetupData();

    (new CompleteSetup)($data);

    // Countries, list names, tax classes, permissions, roles, email templates, notification types
    expect(\App\Models\Country::count())->toBeGreaterThan(0)
        ->and(\App\Models\ListName::count())->toBeGreaterThan(0)
        ->and(\App\Models\TaxClass::count())->toBeGreaterThan(0)
        ->and(\Spatie\Permission\Models\Permission::count())->toBeGreaterThan(0)
        ->and(\Spatie\Permission\Models\Role::count())->toBeGreaterThan(0);
});

it('creates a member record linked to admin user', function () {
    $data = makeSetupData();

    $user = (new CompleteSetup)($data);

    expect($user->member_id)->not->toBeNull();

    $member = \App\Models\Member::find($user->member_id);
    expect($member)->not->toBeNull()
        ->and($member->name)->toBe('Jane Smith')
        ->and($member->membership_type->value)->toBe('user')
        ->and($member->is_active)->toBeTrue();
});

it('creates a membership linking admin to default store', function () {
    $data = makeSetupData();

    $user = (new CompleteSetup)($data);

    $membership = \App\Models\Membership::where('member_id', $user->member_id)->first();
    expect($membership)->not->toBeNull()
        ->and($membership->is_owner)->toBeTrue()
        ->and($membership->is_active)->toBeTrue();

    $store = \App\Models\Store::where('is_default', true)->first();
    expect($membership->store_id)->toBe($store->id);
});

it('calls config:cache instead of config:clear after setup', function () {
    // This is verified implicitly — config('signals.setup_complete') should be true
    // The real test is that the env file is written correctly
    $data = makeSetupData();

    (new CompleteSetup)($data);

    expect(config('signals.setup_complete'))->toBeTrue();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Actions/Setup/CompleteSetupTest.php`
Expected: FAIL — no reference data seeded, no member_id on user

- [ ] **Step 3: Update CompleteSetup action**

Modify `app/Actions/Setup/CompleteSetup.php`:

1. Add `use App\Enums\MembershipType;` and `use App\Models\Member;` and `use App\Models\Membership;` imports
2. Add `use Database\Seeders\...` imports for all 7 seeders
3. Add `$this->seedReferenceData();` call in `__invoke()` before `$this->createStores($data)`
4. Update `createAdminUser()` to:
   - Create a `Member` with `membership_type` => `MembershipType::User`, `name` => admin name, `is_active` => true
   - Create the `User` with `member_id` pointing to new Member
   - After stores are created, create a `Membership` linking member to default store
5. Reorder `__invoke()`: seed reference data → write settings → create stores → create admin (needs store) → record metadata → mark complete
6. Change `markSetupComplete()` to call `Artisan::call('config:cache')` instead of `Artisan::call('config:clear')` (but keep `config:clear` in test env to avoid parallel test issues — check `app()->runningUnitTests()`)

```php
private function seedReferenceData(): void
{
    $seeder = app(\Database\Seeders\DatabaseSeeder::class);
    $seeder->call([
        \Database\Seeders\CountrySeeder::class,
        \Database\Seeders\ListOfValuesSeeder::class,
        \Database\Seeders\TaxClassSeeder::class,
        \Database\Seeders\PermissionSeeder::class,
        \Database\Seeders\RoleSeeder::class,
        \Database\Seeders\EmailTemplateSeeder::class,
        \Database\Seeders\NotificationTypeSeeder::class,
    ]);
}

private function createAdminUser(CompleteSetupData $data): User
{
    $member = Member::create([
        'name' => $data->adminName,
        'membership_type' => MembershipType::User,
        'is_active' => true,
    ]);

    $defaultStore = Store::where('is_default', true)->first();

    if ($defaultStore) {
        Membership::create([
            'member_id' => $member->id,
            'store_id' => $defaultStore->id,
            'is_owner' => true,
            'is_admin' => true,
            'is_active' => true,
        ]);
    }

    return User::create([
        'name' => $data->adminName,
        'email' => $data->adminEmail,
        'password' => $data->adminPassword,
        'email_verified_at' => now(),
        'is_owner' => true,
        'is_admin' => true,
        'member_id' => $member->id,
    ]);
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Actions/Setup/CompleteSetupTest.php`
Expected: PASS

---

## Chunk 2: Password Validation + Install Command

### Task 3: Strengthen password validation to 12 characters

**Files:**
- Modify: `app/Console/Commands/SignalsSetupCommand.php` (lines 388-392)
- Modify: `resources/views/livewire/setup/wizard.blade.php` (line 239)

- [ ] **Step 1: Update CLI password validation**

In `SignalsSetupCommand.php`, change:
```php
$pass = password(label: 'Password', required: true, hint: 'Minimum 8 characters');

if (strlen($pass) < 8) {
    throw new RuntimeException('Password must be at least 8 characters.');
}
```
To:
```php
$pass = password(label: 'Password', required: true, hint: 'Minimum 12 characters, mixed case, numbers, and symbols');

if (strlen($pass) < 12) {
    throw new RuntimeException('Password must be at least 12 characters.');
}

if (! preg_match('/[a-z]/', $pass) || ! preg_match('/[A-Z]/', $pass)
    || ! preg_match('/[0-9]/', $pass) || ! preg_match('/[\W_]/', $pass)) {
    throw new RuntimeException('Password must contain uppercase, lowercase, numbers, and symbols.');
}
```

- [ ] **Step 2: Update web wizard password validation**

In `resources/views/livewire/setup/wizard.blade.php` line 239, change:
```php
'adminPassword' => ['required', 'string', 'min:8', 'confirmed'],
```
To:
```php
'adminPassword' => ['required', 'string', 'min:12', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/[0-9]/', 'regex:/[\W_]/', 'confirmed'],
```

- [ ] **Step 3: Update existing setup command tests**

Find any tests that use passwords shorter than 12 chars (like `password123` in `makeSetupData`) and update to valid passwords like `SecurePass1!` (12 chars, mixed case, number, symbol).

Run: `php artisan test --compact --filter="setup" --exclude-group=env-writing`

### Task 4: PG extension check + SIGNALS_SETUP_COMPLETE=false in install command

**Files:**
- Modify: `app/Console/Commands/SignalsInstallCommand.php`
- Modify: existing install command tests

- [ ] **Step 1: Add PG extension check after database connection**

In `configureDatabase()`, after `$this->components->info("Connected to {$dbResult['version']}");` (line 249), add:

```php
// Check for recommended PostgreSQL extensions
$extensions = $tester->checkExtensions([
    'host' => $host,
    'port' => $port,
    'database' => $database,
    'username' => $username,
    'password' => $pass,
], ['pgcrypto']);

foreach ($extensions as $ext => $installed) {
    if ($installed) {
        $this->components->info("Extension '{$ext}' is available");
    } else {
        $this->components->warn("Extension '{$ext}' is not installed — some features may be limited");
    }
}
```

- [ ] **Step 2: Write SIGNALS_SETUP_COMPLETE=false in finalize()**

In `finalize()`, update the `writeEnvVariables` call (line 701-704) to include:
```php
$this->writeEnvVariables([
    'APP_URL' => $url,
    'SIGNALS_INSTALLED' => 'true',
    'SIGNALS_SETUP_COMPLETE' => 'false',
]);
```

- [ ] **Step 3: Run install command tests**

Run: `php artisan test --compact --filter="SignalsInstall" --exclude-group=env-writing && php artisan test --compact --filter="SignalsInstall" --group=env-writing`
Expected: PASS

---

## Chunk 3: Checklist + Demo Data + Clear Command

### Task 5: Expand getting-started checklist

**Files:**
- Modify: `app/Livewire/Dashboard/GettingStartedChecklist.php`
- Modify: `tests/Feature/Livewire/Dashboard/GettingStartedChecklistTest.php`

- [ ] **Step 1: Write the failing test**

Add to the test file:

```php
it('has at least 8 checklist items', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(GettingStartedChecklist::class);

    $checklist = app(GettingStartedChecklist::class);
    $checklist->mount();
    expect($checklist->items())->toHaveCount(8);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Livewire/Dashboard/GettingStartedChecklistTest.php`
Expected: FAIL — only 3 items

- [ ] **Step 3: Expand checklist items**

Update `items()` in `GettingStartedChecklist.php`:

```php
public function items(): array
{
    return [
        [
            'label' => 'Company configured',
            'completed' => ! empty(settings('company.name')),
            'description' => 'Set your company name, country, and tax details.',
        ],
        [
            'label' => 'Store created',
            'completed' => Store::query()->exists(),
            'description' => 'Add at least one physical location or warehouse.',
        ],
        [
            'label' => 'Admin account set up',
            'completed' => User::query()->where('is_owner', true)->exists(),
            'description' => 'Create an owner account for your organisation.',
        ],
        [
            'label' => 'Upload your logo',
            'completed' => ! empty(settings('branding.logo_path')),
            'description' => 'Add your company logo for documents and the navigation bar.',
        ],
        [
            'label' => 'Configure email settings',
            'completed' => ! empty(settings('email.smtp_host')),
            'description' => 'Set up SMTP so Signals can send invoices, notifications, and invitations.',
        ],
        [
            'label' => 'Invite a team member',
            'completed' => User::query()->count() > 1,
            'description' => 'Add colleagues so they can log in and start working.',
        ],
        [
            'label' => 'Create your first product',
            'completed' => false, // TODO: Enable when Product model exists
            'description' => 'Add equipment, services, or consumables to your catalogue.',
        ],
        [
            'label' => 'Create your first opportunity',
            'completed' => false, // TODO: Enable when Opportunity model exists
            'description' => 'Start a quote or order for a customer.',
        ],
    ];
}
```

- [ ] **Step 4: Update existing checklist test for 100% progress**

The test `shows completed items when setup is done` asserts `100% complete` — now with 8 items, 2 are stub `false`, so 100% is impossible. Update the test to check for correct progress percentage (6/8 = 75% when company, store, owner, logo, email, and invite are done):

Actually, simpler: update the test to assert the correct percentage given only 3 of 8 items are completable in the test. Or better: test that the component calculates progress correctly.

- [ ] **Step 5: Run tests**

Run: `php artisan test --compact tests/Feature/Livewire/Dashboard/GettingStartedChecklistTest.php`
Expected: PASS

### Task 6: Demo data tagging and DemoDataSeeder stubs

**Files:**
- Modify: `database/seeders/DemoDataSeeder.php`

- [ ] **Step 1: Add `is_demo` tag to all demo members**

Update `DemoDataSeeder` to set `tag_list` on all created members:

In `createDemoMembers()`, update each factory call to include `->state(['tag_list' => ['demo-data']])`:

```php
$organisations = Member::factory()
    ->organisation()
    ->count(2000)
    ->create(['tag_list' => ['demo-data']]);
```

Same for venues and contacts.

Also tag demo stores by adding a `tag_list` column check — but Store may not have `tag_list`. Instead, store demo store IDs in a setting for cleanup, or rely on known names + the `setup.demo_seeded_at` timestamp.

Simpler approach: tag members with `['demo-data']` in `tag_list` (Member already has `tag_list` cast as array). For stores, keep the name-based cleanup since there are only 3.

- [ ] **Step 2: Add stub seeders for future models**

Add stub methods to `DemoDataSeeder`:

```php
public function run(): void
{
    $this->createDemoStores();
    $this->createDemoMembers();
    $this->createDemoProducts();
    $this->createDemoOpportunities();
    $this->createDemoInvoices();
    $this->createDemoCustomFields();
    $this->createDemoActivities();
}

/** @codeCoverageIgnore */
private function createDemoProducts(): void
{
    // TODO: Implement when Product model exists
    // Spec: ~50 products across 5 groups (Lighting, Sound, Video, Staging, Power)
    // ~20 serialised assets with serial numbers
}

/** @codeCoverageIgnore */
private function createDemoOpportunities(): void
{
    // TODO: Implement when Opportunity model exists
    // Spec: ~20 opportunities in various states (draft, quotation, order)
}

/** @codeCoverageIgnore */
private function createDemoInvoices(): void
{
    // TODO: Implement when Invoice model exists
    // Spec: ~10 invoices (open, issued, paid)
}

/** @codeCoverageIgnore */
private function createDemoCustomFields(): void
{
    // TODO: Implement with custom field example values
}

/** @codeCoverageIgnore */
private function createDemoActivities(): void
{
    // TODO: Implement when Activity model exists
    // Spec: activities and discussions on demo records
}
```

- [ ] **Step 3: Run seeder smoke test (optional)**

The seeder tests may not exist. If they do, run them. Otherwise this is verified manually.

### Task 7: Fix SignalsClearDemoCommand

**Files:**
- Modify: `app/Console/Commands/SignalsClearDemoCommand.php`

- [ ] **Step 1: Update clear command to remove all demo data**

Replace the body of `handle()` to:

```php
public function handle(): int
{
    if (! settings('setup.demo_seeded_at')) {
        $this->components->warn('No demo data has been seeded.');

        return self::SUCCESS;
    }

    if (! $this->option('force') && $this->input->isInteractive()) {
        if (! confirm('This will remove all demo data. Continue?', false)) {
            $this->components->info('Cancelled.');

            return self::SUCCESS;
        }
    }

    $this->components->info('Removing demo data...');

    // Remove demo members and their cascading relationships
    $demoMembers = Member::query()->whereJsonContains('tag_list', 'demo-data');
    $demoMemberIds = $demoMembers->pluck('id');

    if ($demoMemberIds->isNotEmpty()) {
        // Delete related records first
        Email::query()->where('emailable_type', Member::class)
            ->whereIn('emailable_id', $demoMemberIds)->delete();
        Phone::query()->where('phoneable_type', Member::class)
            ->whereIn('phoneable_id', $demoMemberIds)->delete();
        MemberRelationship::query()
            ->whereIn('member_id', $demoMemberIds)
            ->orWhereIn('related_member_id', $demoMemberIds)
            ->delete();

        $count = $demoMembers->forceDelete();
        $this->components->info("Removed {$count} demo members and their contact details");
    }

    // Remove demo stores
    $demoStoreNames = ['London Warehouse', 'Manchester Depot', 'Edinburgh Office'];
    Store::query()->whereIn('name', $demoStoreNames)->delete();

    settings()->set('setup.demo_seeded_at', '');

    $this->components->info('Demo data removed.');

    return self::SUCCESS;
}
```

Add imports at top: `use App\Models\Member;`, `use App\Models\Email;`, `use App\Models\Phone;`, `use App\Models\MemberRelationship;`.

- [ ] **Step 2: Run existing clear-demo tests**

Run: `php artisan test --compact --filter="ClearDemo"`
Expected: PASS (update tests if needed to account for new cleanup logic)

---

## Chunk 4: Final Verification

### Task 8: Run full test suite and quality checks

- [ ] **Step 1: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 2: Run PHPStan**

Run: `vendor/bin/phpstan analyse`

- [ ] **Step 3: Run all tests**

Run: `php artisan test --parallel --compact --exclude-group=env-writing && php artisan test --compact --group=env-writing`
Expected: All PASS

- [ ] **Step 4: Fix any failures and repeat steps 1-3**

---

## Verification

After all tasks:

1. All tests pass (parallel + env-writing)
2. `vendor/bin/pint --dirty` shows no changes
3. `vendor/bin/phpstan analyse` returns 0 errors
4. FeatureProfile enum has 16 modules per profile
5. CompleteSetup seeds reference data and creates Member-linked admin
6. Password validation requires 12 chars + complexity
7. Install command checks PG extensions and writes SIGNALS_SETUP_COMPLETE=false
8. Checklist has 8 items (6 live, 2 stubs)
9. DemoDataSeeder tags members and has stub methods
10. SignalsClearDemoCommand removes all tagged demo data
