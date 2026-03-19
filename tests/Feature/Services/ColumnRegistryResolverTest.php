<?php

use App\Services\ColumnRegistryResolver;
use App\Views\Column;
use App\Views\ColumnRegistry;
use App\Views\MemberColumnRegistry;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->resolver = new ColumnRegistryResolver;
});

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
