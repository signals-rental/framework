# Signals Framework - Claude Code Guidelines

## Stack & Versions

- **PHP** 8.4 / **Laravel** 12 (streamlined structure)
- **Livewire** 4
- **Pest** 4 (with browser testing)
- **Laravel Pint** (preset: `laravel`)
- **Tailwind CSS** 4 via `@tailwindcss/vite`
- **Vite** 7 with `laravel-vite-plugin`

## Commands

```bash
# Setup
composer setup              # install deps, generate key, migrate, build frontend

# Development
composer dev                # runs server, queue, pail, vite concurrently

# Frontend
npm run dev                 # vite dev server
npm run build               # production build

# Linting
vendor/bin/pint --dirty     # fix formatting on changed files
vendor/bin/pint             # fix all files

# Testing
php artisan test                              # run all tests
php artisan test tests/Feature/ExampleTest.php  # run a single file
php artisan test --filter=testName            # filter by name
```

## Architecture (Laravel 12)

This project uses the Laravel 11+ streamlined structure:

- `bootstrap/app.php` - registers middleware, exceptions, and routing
- `bootstrap/providers.php` - application service providers
- No `app/Http/Kernel.php` or `app/Console/Kernel.php`
- No middleware files in `app/Http/Middleware/` by default
- Commands in `app/Console/Commands/` auto-register
- Routes: `routes/web.php`, `routes/console.php`

## Conventions

### General

- Follow existing code conventions. Check sibling files for structure, approach, naming.
- Use descriptive names: `isRegisteredForDiscounts`, not `discount()`.
- Reuse existing components before creating new ones.
- Don't create new base folders or change dependencies without approval.
- Don't create documentation files unless explicitly requested.

### PHP

- Always use curly braces for control structures, even single-line.
- Use PHP 8 constructor property promotion. No empty zero-parameter constructors.
- Always use explicit return types and parameter type hints.
- Prefer PHPDoc blocks over inline comments. Only comment genuinely complex logic.
- Add array shape type definitions in PHPDoc when appropriate.
- Enum keys should be TitleCase: `FavoritePerson`, `Monthly`.

### Laravel

- Use `php artisan make:*` commands with `--no-interaction` to create files.
- Use `artisan make:class` for generic PHP classes.
- Prefer Eloquent relationships over raw queries. Avoid `DB::`; use `Model::query()`.
- Use eager loading to prevent N+1 problems.
- Create Form Request classes for validation (not inline). Include rules and custom messages.
- Use named routes and `route()` for URL generation.
- Never use `env()` outside config files. Use `config()` instead.
- Use queued jobs with `ShouldQueue` for long-running operations.
- Use gates, policies, and Sanctum for auth.
- When creating models, also create factories and seeders.
- Use Eloquent API Resources for APIs.
- Column modifications in migrations must re-declare all existing attributes.
- Use `casts()` method on models (not `$casts` property). Follow existing model conventions.

### Livewire

- Create components with `php artisan make:livewire`.
- Components require a single root element.
- State lives on the server. Always validate and authorize in Livewire actions.
- Use `wire:loading`, `wire:dirty` for loading states.
- Always add `wire:key` in loops.
- Use lifecycle hooks: `mount()`, `updatedFoo()`.

### Testing (Pest 4)

- All tests use Pest. Create with `php artisan make:test --pest <name>` (add `--unit` for unit tests).
- Tests live in `tests/Feature/` and `tests/Unit/`. Browser tests in `tests/Browser/`.
- Never remove tests without approval.
- Test happy paths, failure paths, and edge cases.
- Use model factories in tests. Check for existing factory states before manual setup.
- Use specific assertion methods: `assertForbidden()`, not `assertStatus(403)`.
- Use datasets for tests with repeated data (especially validation rules).
- Import mocks via `use function Pest\Laravel\mock;` or use `$this->mock()` per existing convention.
- Run the minimal relevant tests after changes. Ask before running the full suite.

### Formatting

- Run `vendor/bin/pint --dirty` before finalizing changes.

## Git Workflow

- Do not auto-commit. Always ask before committing.
- Always ask before pushing to remote.
