<?php

use App\Models\CustomView;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ViewSeeder;

it('creates a custom view with factory', function () {
    $view = CustomView::factory()->create();

    expect($view)->toBeInstanceOf(CustomView::class)
        ->and($view->columns)->toBeArray()
        ->and($view->filters)->toBeArray();
});

it('casts columns and filters to arrays', function () {
    $view = CustomView::factory()->create([
        'columns' => ['name', 'email'],
        'filters' => [['field' => 'is_active', 'predicate' => 'eq', 'value' => true]],
    ]);

    expect($view->columns)->toBe(['name', 'email'])
        ->and($view->filters)->toHaveCount(1)
        ->and($view->filters[0]['field'])->toBe('is_active');
});

it('casts is_default to boolean', function () {
    $view = CustomView::factory()->create(['is_default' => true]);

    expect($view->is_default)->toBeTrue()->toBeBool();
});

it('casts per_page to integer', function () {
    $view = CustomView::factory()->create(['per_page' => 50]);

    expect($view->per_page)->toBe(50)->toBeInt();
});

it('casts config to array', function () {
    $view = CustomView::factory()->create(['config' => ['show_totals' => true]]);

    expect($view->config)->toBe(['show_totals' => true]);
});

it('belongs to a user', function () {
    $user = User::factory()->create();
    $view = CustomView::factory()->create(['user_id' => $user->id]);

    expect($view->user)->toBeInstanceOf(User::class)
        ->and($view->user->id)->toBe($user->id);
});

it('has nullable user for system views', function () {
    $view = CustomView::factory()->system()->create();

    expect($view->user_id)->toBeNull()
        ->and($view->user)->toBeNull();
});

it('has roles relationship for shared views', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $view = CustomView::factory()->shared()->create();
    $role = \Spatie\Permission\Models\Role::findByName('Admin', 'web');
    $view->roles()->attach($role);

    expect($view->roles)->toHaveCount(1)
        ->and($view->roles->first()->name)->toBe('Admin');
});

it('scopes to entity type', function () {
    CustomView::factory()->create(['entity_type' => 'members']);
    CustomView::factory()->create(['entity_type' => 'opportunities']);

    expect(CustomView::forEntity('members')->count())->toBe(1)
        ->and(CustomView::forEntity('opportunities')->count())->toBe(1);
});

it('scopes to system defaults', function () {
    CustomView::factory()->system()->create(['entity_type' => 'members']);
    CustomView::factory()->create(['entity_type' => 'members']);

    expect(CustomView::systemDefaults()->count())->toBe(1);
});

it('scopes system defaults to only default views', function () {
    CustomView::factory()->create([
        'visibility' => 'system',
        'user_id' => null,
        'is_default' => false,
    ]);

    expect(CustomView::systemDefaults()->count())->toBe(0);
});

it('scopes visible views to user including system views', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Admin');

    // System view - visible to all
    CustomView::factory()->system()->create(['entity_type' => 'members']);

    $visible = CustomView::visibleTo($user)->get();

    expect($visible)->toHaveCount(1)
        ->and($visible->first()->visibility)->toBe('system');
});

it('scopes visible views to user including personal views', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    // Personal view for $user
    CustomView::factory()->create(['entity_type' => 'members', 'user_id' => $user->id]);
    // Personal view for other user - not visible
    CustomView::factory()->create(['entity_type' => 'members', 'user_id' => $otherUser->id]);

    $visible = CustomView::visibleTo($user)->get();

    expect($visible)->toHaveCount(1)
        ->and($visible->first()->user_id)->toBe($user->id);
});

it('scopes visible views to user including shared role views', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Admin');

    // Shared view for Admin role - visible to $user
    $shared = CustomView::factory()->shared()->create(['entity_type' => 'members']);
    $shared->roles()->attach(\Spatie\Permission\Models\Role::findByName('Admin', 'web'));

    // Shared view for a different role - not visible
    $otherShared = CustomView::factory()->shared()->create(['entity_type' => 'members']);
    $otherShared->roles()->attach(\Spatie\Permission\Models\Role::findByName('Read Only', 'web'));

    $visible = CustomView::visibleTo($user)->get();

    expect($visible)->toHaveCount(1)
        ->and($visible->first()->id)->toBe($shared->id);
});

it('scopes visible views combining all visibility types', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Admin');
    $otherUser = User::factory()->create();

    // System view
    CustomView::factory()->system()->create(['entity_type' => 'members']);
    // Personal view for $user
    CustomView::factory()->create(['entity_type' => 'members', 'user_id' => $user->id]);
    // Personal view for other user - not visible
    CustomView::factory()->create(['entity_type' => 'members', 'user_id' => $otherUser->id]);
    // Shared view for Admin role
    $shared = CustomView::factory()->shared()->create(['entity_type' => 'members']);
    $shared->roles()->attach(\Spatie\Permission\Models\Role::findByName('Admin', 'web'));

    $visible = CustomView::visibleTo($user)->get();

    expect($visible)->toHaveCount(3); // system + personal + shared
});

it('seeds system views via ViewSeeder', function () {
    $this->seed(ViewSeeder::class);

    expect(CustomView::where('entity_type', 'members')->where('visibility', 'system')->count())->toBe(5);
    $allMembers = CustomView::where('name', 'All Members')->first();
    expect($allMembers)->not->toBeNull();
    expect($allMembers->is_default)->toBeTrue();
});

it('seeds named system views correctly', function () {
    $this->seed(ViewSeeder::class);

    $views = CustomView::where('entity_type', 'members')->pluck('name')->all();

    expect($views)->toContain('All Members')
        ->toContain('Organisations Only')
        ->toContain('Contacts Only')
        ->toContain('Active Venues')
        ->toContain('Inactive Members');
});

it('seeds views with filters', function () {
    $this->seed(ViewSeeder::class);

    $orgView = CustomView::where('name', 'Organisations Only')->first();

    expect($orgView->filters)->toHaveCount(1)
        ->and($orgView->filters[0]['field'])->toBe('membership_type')
        ->and($orgView->filters[0]['value'])->toBe('organisation');
});

it('seeds only one default view per entity type', function () {
    $this->seed(ViewSeeder::class);

    $defaults = CustomView::where('entity_type', 'members')
        ->where('is_default', true)
        ->count();

    expect($defaults)->toBe(1);
});
