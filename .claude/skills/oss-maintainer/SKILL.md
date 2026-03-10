---
name: oss-maintainer
description: "Open source project quality review. Activates after completing significant features, before PRs or releases, or when explicitly invoked via /oss-review. Covers code simplicity, duplication detection, documentation currency (user docs, API docs, PHPDoc), component library compliance (s-* classes, x-signals.* components, Flux UI), test coverage gaps, and architectural alignment with Signals patterns."
license: MIT
metadata:
  author: signals
---

# OSS Maintainer Review

## When to Apply

Activate this skill when:

- A significant feature or domain slice has been completed
- Before creating a pull request or release
- The user explicitly invokes `/oss-review`
- After implementing a framework plan phase
- When reviewing work done by subagents before merging

## Workflow

### Phase 1: Scope

Determine what changed. Use the approach that matches the context:

1. **Uncommitted changes (default):** Run `git diff --name-only` and `git diff --cached --name-only` to get the file list.
2. **Commit range:** If the user specifies a range (e.g. `HEAD~5..HEAD`), use `git diff --name-only {range}`.
3. **User-specified files:** If the user names specific files or directories, use those.

Build a categorized file list:
- **PHP files** — `app/`, `tests/`, `config/`, `routes/`
- **Blade/CSS files** — `resources/views/`, `resources/css/`
- **Documentation files** — `docs/`, `framework-plans/`
- **Other** — migrations, config, etc.

Report the scope before proceeding:
> Reviewing {N} files across {categories}...

### Phase 2: Simplicity & Duplication

For each changed PHP file, check against the simplicity checklist in `references/checklist.md`.

**Simplicity checks:**
- Flag helpers, utilities, or abstractions created for one-time operations
- Flag feature flags or backwards-compat shims where the code could just change
- Flag premature abstractions — three similar lines are better than a shared helper used once
- Verify action classes are single-responsibility (one public `__invoke` method doing one thing)
- Check DTOs aren't over-validated (trust internal code, validate at system boundaries)

**Duplication checks:**
- Search `app/Services/` for existing services that handle the same concern
- Search `app/Actions/` for existing actions that do similar work
- Check if the concern should be registered in a registry rather than hardcoded
- Search for similar Blade partials or components that already exist
- Use `Grep` to find functions/methods with similar names or signatures in the codebase

Flag findings as:
- **Critical:** Exact duplication of existing functionality
- **Important:** Similar logic exists that could be reused or extracted
- **Suggestion:** Minor simplification opportunities

### Phase 3: Documentation Currency

Check three documentation layers:

**Layer 1 — User docs (`docs/`):**
- Read `docs/documentation.json` to understand the existing docs manifest
- For each changed feature area, check if a corresponding docs page exists
- Flag new routes, settings, permissions, or user-facing features that lack documentation
- Flag existing docs pages that reference code/APIs that changed (may be stale)

**Layer 2 — API docs (Scramble):**
- For new/modified API controllers in `app/Http/Controllers/Api/`, verify:
  - PHPDoc `@param` tags on controller methods
  - Typed return values (response DTOs)
  - `@response` tags for non-standard responses
- Flag controllers missing PHPDoc that Scramble needs for accurate OpenAPI generation

**Layer 3 — Code PHPDoc:**
- For changed classes, verify public methods have:
  - Return type declarations
  - Parameter type hints
  - PHPDoc `@param` with array shapes for complex types (e.g. `@param array{name: string, value: int}`)
- Do NOT flag private/protected methods unless they have complex signatures

Flag findings as:
- **Critical:** New user-facing feature with no documentation at all
- **Important:** API endpoint missing PHPDoc for Scramble; stale docs referencing changed code
- **Suggestion:** Missing array shape PHPDoc on complex types

### Phase 4: Component Library Compliance

For changed Blade and CSS files, check against `framework-plans/component-library.md` (read it at runtime for the current component inventory).

**Checks:**
- Verify use of `s-*` CSS classes — not custom CSS that duplicates existing components
- Verify use of `<x-signals.*>` Blade components for structural containers
- Verify use of `<flux:*>` components for form inputs, modals, buttons where available
- Check that no `s-` tokens appear in page `<style>` blocks (must be in `components.css`)
- Verify design tokens are used: `--font-display`, `--font-sans`, `--font-mono`, `--brand-primary`, `--brand-accent`
- Flag raw HTML where a Blade component or CSS class exists for that pattern (tables, badges, buttons, cards, etc.)

Flag findings as:
- **Critical:** Custom CSS duplicating an existing `s-*` component; `s-` tokens in page `<style>` blocks
- **Important:** Raw HTML where `<x-signals.*>` or `<flux:*>` component exists
- **Suggestion:** Opportunity to use a design token instead of a hardcoded value

### Phase 5: Test Coverage

For each changed file in `app/`, check for corresponding tests:

**Action classes (`app/Actions/`):**
- Must have a test that calls the action directly with a DTO: `(new ActionClass)($dto)`
- Must test happy path, authorization (`assertForbidden`), and validation failures

**Livewire components (`app/Livewire/`):**
- Must have `Livewire::test()` or `Volt::test()` coverage
- Must test rendering, actions, and state changes

**API controllers (`app/Http/Controllers/Api/`):**
- Must have HTTP tests with authentication (Sanctum)
- Must verify response shape matches expected DTO structure

**Models (`app/Models/`):**
- Must have a factory
- Relationships should be tested

**Middleware, Jobs, Events, Listeners:**
- Check for at least one test covering the primary behavior

Flag findings as:
- **Critical:** New action class or API endpoint with zero test coverage
- **Important:** Missing failure/authorization test paths
- **Suggestion:** Missing edge case or dataset-based validation tests

### Phase 6: Report

Generate the review report with categorized findings.

**Inline output:** Display a summary grouped by severity (Critical / Important / Suggestion) with file references.

**Saved report:** Write a detailed report to `.claude/reviews/oss-review-{YYYY-MM-DD}.md` using this template:

```markdown
# OSS Review — {YYYY-MM-DD}

## Scope
{number of files, feature summary, git range if applicable}

### Files Reviewed
{categorized file list}

## Findings

### Critical
{blocking issues — must fix before merge}

### Important
{should-fix issues — recommended before merge}

### Suggestions
{nice-to-have improvements — can be deferred}

## Documentation Status

| Layer | Status | Details |
|-------|--------|---------|
| User docs | {compliant/needs-update/missing} | {specifics} |
| API docs (Scramble) | {compliant/needs-update/missing} | {specifics} |
| Code PHPDoc | {compliant/needs-update/missing} | {specifics} |

## Component Library Compliance
{findings or "All compliant"}

## Test Coverage

| Area | Status | Gap |
|------|--------|-----|
| {file/class} | {covered/partial/missing} | {what's missing} |

## Agent Reviews Dispatched
{list of agents run and their key findings, filled after Phase 7}

## Resolution Status
{updated after Phase 8 fix loop}
```

### Phase 7: Orchestrate Review Agents

Based on what changed, dispatch existing review agents in parallel using the `Task` tool:

**Always run:**
- `pr-review-toolkit:code-reviewer` — general code quality against CLAUDE.md standards

**Conditional dispatch:**
- If Blade/CSS files changed → `pr-review-toolkit:code-simplifier`
- If error handling code touched (try/catch, rescue, fallback) → `pr-review-toolkit:silent-failure-hunter`
- If test files written or changed → `pr-review-toolkit:pr-test-analyzer`
- If new types, DTOs, or enums added → `pr-review-toolkit:type-design-analyzer`
- If comments or PHPDoc added/changed → `pr-review-toolkit:comment-analyzer`
- If documentation gaps found in Phase 3 → invoke `generate-docs` skill to create missing pages

Run independent agents in parallel. Collect results and merge key findings into the saved report under "Agent Reviews Dispatched".

Present a unified summary combining OSS-specific findings (Phases 2-5) with agent findings.

### Phase 8: Fix Loop

If Critical or Important findings exist:

1. **Offer to fix:** Present the fixable issues and ask the user if they want automated fixes applied.
2. **Apply fixes:** For each approved fix:
   - Simplicity/duplication: refactor to use existing code, remove unnecessary abstractions
   - Documentation: generate missing PHPDoc, update stale docs, invoke `generate-docs` skill
   - Component library: replace raw HTML with proper `s-*` classes or `<x-signals.*>` components
   - Test coverage: generate missing test stubs (but don't write fake assertions — flag for manual completion)
3. **Re-run quality gate:** After fixes, run the pre-commit checks:
   - `php artisan test --parallel --compact --exclude-group=env-writing`
   - `vendor/bin/pint --dirty --format agent`
   - `vendor/bin/phpstan analyse`
4. **Update report:** Mark resolved findings in the saved report with resolution status.

If all Critical and Important findings are resolved (or accepted by the user), report success and suggest committing.
