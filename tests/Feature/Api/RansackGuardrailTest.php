<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ViewSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;

/**
 * Guardrails for todo #84 — lock in the Ransack consistency fixes:
 *  1. Every $allowedFilters/$allowedSorts entry must be a real DB column
 *     (would have caught the original `type` virtual-attribute defect).
 *  2. A bogus `q` filter must never 500 on a list endpoint (locks in #77's
 *     whitelist enforcement across the view-backed endpoints).
 */
it('only whitelists real DB columns in allowedFilters and allowedSorts', function () {
    $problems = [];

    foreach (Finder::create()->files()->in(app_path('Http/Controllers/Api/V1'))->name('*.php') as $file) {
        $class = 'App\\Http\\Controllers\\Api\\V1\\'.$file->getFilenameWithoutExtension();

        if (! class_exists($class)) {
            continue;
        }

        $ref = new ReflectionClass($class);

        if ($ref->isAbstract() || ! $ref->hasMethod('modelClass')) {
            continue;
        }

        /** @var array<string, mixed> $defaults */
        $defaults = $ref->getDefaultProperties();
        /** @var list<string> $fields */
        $fields = array_unique([
            ...($defaults['allowedFilters'] ?? []),
            ...($defaults['allowedSorts'] ?? []),
        ]);

        if ($fields === []) {
            continue;
        }

        $instance = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('modelClass');
        $method->setAccessible(true);
        /** @var class-string<Model> $modelClass */
        $modelClass = $method->invoke($instance);

        $table = (new $modelClass)->getTable();
        $columns = Schema::getColumnListing($table);

        foreach ($fields as $field) {
            if (! in_array($field, $columns, true)) {
                $problems[] = "{$class}: '{$field}' is not a column on '{$table}'";
            }
        }
    }

    expect($problems)->toBe([]);
});

it('never 500s on a bogus filter across list endpoints', function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ViewSeeder::class); // seeds the system default views (the bypass path)

    $owner = User::factory()->owner()->create();
    $token = $owner->createToken('test', [
        'products:read', 'stock:read', 'activities:read', 'members:read', 'rates:read',
    ])->plainTextToken;

    $endpoints = [
        'products', 'product_groups', 'stock_levels', 'activities', 'members', 'rate_definitions',
    ];

    foreach ($endpoints as $endpoint) {
        $status = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/{$endpoint}?q[zzz_bogus_uat_eq]=1")
            ->getStatusCode();

        expect($status)->toBe(200, "GET /api/v1/{$endpoint} with a bogus filter returned {$status}");
    }
});
