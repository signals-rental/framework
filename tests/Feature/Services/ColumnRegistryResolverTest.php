<?php

use App\Services\ColumnRegistryResolver;
use App\Views\Column;
use App\Views\ColumnRegistry;
use App\Views\MemberColumnRegistry;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->resolver = new ColumnRegistryResolver;
});

/**
 * Discover every concrete ColumnRegistry subclass under app/Views, deriving the
 * class name from its path relative to app/Views so registries in subdirectories
 * (e.g. app/Views/Foo/BarColumnRegistry.php) are not silently skipped.
 *
 * @return list<class-string<ColumnRegistry>>
 */
function discoverColumnRegistries(): array
{
    $registries = [];

    foreach (File::allFiles(app_path('Views')) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relative = str_replace('.php', '', $file->getRelativePathname());
        $class = 'App\\Views\\'.str_replace('/', '\\', $relative);

        if (! class_exists($class) || ! is_subclass_of($class, ColumnRegistry::class)) {
            continue;
        }

        if ((new ReflectionClass($class))->isAbstract()) {
            continue;
        }

        $registries[] = $class;
    }

    return $registries;
}

it('resolves the members column registry', function () {
    $registry = $this->resolver->resolve('members');

    expect($registry)->toBeInstanceOf(MemberColumnRegistry::class);
});

it('returns null for unknown entity type', function () {
    $registry = $this->resolver->resolve('nonexistent');

    expect($registry)->toBeNull();
});

it('caches resolved instances', function () {
    $first = $this->resolver->resolve('members');
    $second = $this->resolver->resolve('members');

    expect($first)->toBe($second);
});

it('registers a new column registry for an entity type', function () {
    $stubRegistry = new class extends ColumnRegistry
    {
        public function entityType(): string
        {
            return 'widgets';
        }

        public function modelClass(): string
        {
            return 'App\\Models\\Widget';
        }

        protected function columns(): array
        {
            return [
                Column::make('name')->label('Name')->sortable(),
            ];
        }
    };

    $this->resolver->register('widgets', $stubRegistry::class);

    $resolved = $this->resolver->resolve('widgets');

    expect($resolved)->toBeInstanceOf($stubRegistry::class)
        ->and($resolved->allColumns())->toHaveKey('name');
});

it('validates columns successfully for known entity type', function () {
    $this->resolver->validateColumns('members', ['name', 'email']);
})->throwsNoExceptions();

it('throws validation exception for invalid columns', function () {
    $this->resolver->validateColumns('members', ['name', 'totally_fake_column']);
})->throws(ValidationException::class);

it('passes validation silently for unknown entity type', function () {
    $this->resolver->validateColumns('nonexistent', ['anything', 'goes']);
})->throwsNoExceptions();

it('throws validation exception for invalid sort column', function () {
    $this->resolver->validateColumns('members', ['name'], 'totally_fake_sort_column');
})->throws(ValidationException::class);

it('validates a valid sort column without error', function () {
    $this->resolver->validateColumns('members', ['name'], 'name');
})->throwsNoExceptions();

it('validates columns error message contains invalid key names', function () {
    try {
        $this->resolver->validateColumns('members', ['name', 'bad_col_1', 'bad_col_2']);
    } catch (ValidationException $e) {
        expect($e->errors()['columns'][0])
            ->toContain('bad_col_1')
            ->toContain('bad_col_2');

        return;
    }

    $this->fail('Expected ValidationException was not thrown.');
});

it('validates sort column error message contains the column name', function () {
    try {
        $this->resolver->validateColumns('members', ['name'], 'invalid_sort');
    } catch (ValidationException $e) {
        expect($e->errors()['sort_column'][0])->toContain('invalid_sort');

        return;
    }

    $this->fail('Expected ValidationException was not thrown.');
});

/**
 * Guards against the class of defect where a module ships a ColumnRegistry but
 * forgets to register it in ColumnRegistryResolver::$map — which silently makes
 * custom-view column validation skip for that entity (invalid column keys could
 * be saved). This is exactly how the Phase-2 product/activity/stock/group
 * registries existed yet were missing from the resolver map.
 *
 * Resolving each registry's own entityType() back through the resolver also
 * catches typo-key drift: it verifies the MAP KEY equals the registry's
 * entityType(), not mere presence of some key.
 */
it('maps every concrete ColumnRegistry subclass under its own entityType key', function () {
    $resolver = app(ColumnRegistryResolver::class);

    $missing = [];

    foreach (discoverColumnRegistries() as $class) {
        /** @var ColumnRegistry $instance */
        $instance = app($class);
        $resolved = $resolver->resolve($instance->entityType());

        if (! $resolved instanceof $class) {
            $missing[] = "{$class} ({$instance->entityType()})";
        }
    }

    expect($missing)->toBe([]);
});

it('resolves a registry whose entityType matches the registered key', function (string $entityType) {
    $registry = app(ColumnRegistryResolver::class)->resolve($entityType);

    expect($registry)->not->toBeNull()
        ->and($registry->entityType())->toBe($entityType);
})->with(app(ColumnRegistryResolver::class)->entityTypes());

it('returns null for an unregistered entity type', function () {
    expect(app(ColumnRegistryResolver::class)->resolve('nonexistent_entities'))->toBeNull();
});
