# OSS Maintainer Review Checklists

Detailed checklist items referenced by the main SKILL.md during each review phase.

## Simplicity Checklist

- [ ] No helper/utility created for a one-time operation — three similar lines are better than a premature abstraction
- [ ] No feature flags or backwards-compat shims when the code can just change directly
- [ ] Action classes are single-responsibility — one `__invoke` method, one clear purpose
- [ ] DTOs aren't over-validated — trust internal code, only validate at system boundaries (user input, external APIs)
- [ ] No unnecessary error handling for scenarios that can't happen — trust framework guarantees
- [ ] No `_unused` variable renames, `// removed` comments, or re-exports for backwards compatibility
- [ ] No design for hypothetical future requirements — minimum complexity for the current task
- [ ] No over-engineered configuration — hardcoded values are fine for things that rarely change
- [ ] No wrapper classes that just delegate to a single dependency without adding value

## Duplication Checklist

- [ ] Search `app/Services/` for existing service that handles this concern
- [ ] Search `app/Actions/` for existing action that does similar work
- [ ] Check if the concern should use a registry pattern — registered, not hardcoded
- [ ] Check if similar Blade partial or `<x-signals.*>` component already exists
- [ ] Search for functions/methods with similar names or signatures across the codebase
- [ ] Verify new DTOs don't duplicate fields that an existing DTO already covers
- [ ] Check `app/ValueObjects/` for existing value objects (Money, Timezone, etc.)
- [ ] Verify event names don't overlap with existing events in `app/Events/`

## Documentation Checklist

### User Docs (`docs/`)

- [ ] New route/page → needs a docs page or update to an existing page
- [ ] New setting → documented in the relevant settings docs section
- [ ] New permission → documented in the permissions docs
- [ ] New API endpoint → documented or Scramble-discoverable via PHPDoc
- [ ] Changed behavior → existing docs page updated to reflect the change
- [ ] New CLI command → documented with usage examples

### API Docs (Scramble)

- [ ] Controller methods have `@param` PHPDoc tags for all parameters
- [ ] Controller methods have typed return values (response DTOs)
- [ ] Non-standard responses have `@response` PHPDoc tags
- [ ] Query parameters documented via `@queryParam` or typed request objects
- [ ] Enum parameters show allowed values in PHPDoc

### Code PHPDoc

- [ ] Public methods on changed classes have return type declarations
- [ ] Public methods have parameter type hints
- [ ] Complex array parameters have `@param array{key: type}` shape definitions
- [ ] Collections have `@param Collection<int, Model>` generic types
- [ ] Lazy-loaded DTO properties have `/** @var Lazy|Type[] */` annotations

## Component Library Checklist

### Structural Containers (use `<x-signals.*>`)

- [ ] Page headers use `<x-signals.page-header>` — not raw `<div>` with manual styling
- [ ] Cards use `<x-signals.card>` — not raw `<div class="rounded border...">`
- [ ] Panels use `<x-signals.panel>` — not custom panel markup
- [ ] Tables use `<x-signals.table-wrap>` wrapper — not raw `<div class="overflow-...">`
- [ ] Toolbars use `<x-signals.toolbar>` — not manual flex layouts for toolbar patterns
- [ ] Empty states use `<x-signals.empty>` — not custom empty state markup
- [ ] Form sections use `<x-signals.form-section>` — not raw fieldset/div grouping
- [ ] Modals use `<flux:modal>` — not custom modal implementations

### Inline Elements (use `s-*` CSS classes)

- [ ] Tables use `s-table-wrap` + `s-table` — not custom table styling
- [ ] Badges use `s-badge` + color variant — not custom badge markup
- [ ] Status indicators use `s-status` + color variant — not custom dots/icons
- [ ] Buttons use `s-btn` or `<flux:button>` — not custom button styling
- [ ] Chips use `s-chip` — not custom tag/filter markup
- [ ] Tabs use `s-tab` — not custom tab implementations
- [ ] Keyboard hints use `s-kbd` — not custom kbd styling
- [ ] Section labels use `s-section-label` — not custom label markup

### Form Components (use `<flux:*>`)

- [ ] Text inputs use `<flux:input>` — not raw `<input>`
- [ ] Select dropdowns use `<flux:select>` — not raw `<select>` or `s-select`
- [ ] Checkboxes use `<flux:checkbox>` or `<x-signals.checkbox>` — not raw `<input type="checkbox">`
- [ ] Buttons use `<flux:button>` — not raw `<button>` for form actions
- [ ] Modals use `<flux:modal>` — not custom modal implementations

### CSS Rules

- [ ] No `s-` tokens in page `<style>` blocks — all `s-` CSS must be in `components.css`
- [ ] No custom CSS that duplicates an existing `s-*` component
- [ ] Design tokens used where applicable: `--font-display`, `--font-sans`, `--font-mono`, `--brand-primary`, `--brand-accent`
- [ ] No hardcoded colors where a design token or Tailwind utility exists

## Test Coverage Checklist

### Action Classes

- [ ] Test calls the action directly with a DTO: `(new ActionClass)($dto)`
- [ ] Happy path tested — correct result returned
- [ ] Authorization tested — `assertForbidden()` for unauthorized users
- [ ] Validation failures tested — invalid DTO data rejected
- [ ] Side effects verified — events fired (`Event::fake()`), jobs dispatched (`Queue::fake()`)

### Livewire Components

- [ ] Component renders without errors: `Livewire::test(Component::class)->assertOk()`
- [ ] User interactions tested: `->call('method')`, `->set('property', 'value')`
- [ ] State changes verified: `->assertSet('property', 'expected')`
- [ ] Authorization tested: unauthorized users can't access the component

### API Endpoints

- [ ] HTTP test with Sanctum authentication: `actingAs($user, ['ability:scope'])`
- [ ] Response shape matches expected DTO structure
- [ ] Pagination works correctly for collection endpoints
- [ ] Filtering (Ransack) tested for key predicates
- [ ] Unauthorized access returns 401/403

### Models

- [ ] Factory exists and creates valid models
- [ ] Relationships return correct types
- [ ] Scopes/accessors/mutators tested
- [ ] Casts work correctly

### General

- [ ] Use model factories — check for existing states before manual setup
- [ ] Use specific assertions: `assertForbidden()`, not `assertStatus(403)`
- [ ] Use datasets for repeated validation rule testing
- [ ] Isolate side effects: `Queue::fake()`, `Event::fake()`, `Notification::fake()`

## Architecture Alignment Checklist

- [ ] Business logic lives in action classes — not in controllers or Livewire components
- [ ] Data flows through DTOs — not raw arrays or request objects beyond the controller
- [ ] Registry pattern used for extensible feature registration
- [ ] No tenant awareness in core code — no `tenant_id`, no tenancy helpers
- [ ] Settings accessed via `settings('group.key')` — not `env()` or `config()` for runtime values
- [ ] Money stored as integers (minor units) — not floats or decimals
- [ ] Permissions follow `resource.action` naming — API abilities use `resource:action`
- [ ] Events follow `{Entity}{PastTenseVerb}` naming
- [ ] Jobs implement `ShouldQueue` for long-running operations
- [ ] No `DB::` facade usage — use `Model::query()` instead
