# OSS Review — 2026-03-19

## Scope
4 commits (`30af9d0f..HEAD`), 68 files across Members CRM redesign, Command Palette, Custom Fields enforcement, and CRMS API schema alignment.

### Commits
- `17f3fb8` Align Member model with CRMS API schema and improve test coverage
- `80cc1d8` Add command palette with live member search and keyboard shortcuts
- `ec11b6d` Redesign member show page with CRM-style 3-column layout and notifications pane
- `f32a8d2` Enforce custom field properties: defaults, required, searchable, active

### Files Reviewed
- **PHP (28):** Actions (6), Data/DTOs (3), Controllers (2), Models (2), Services (4), Commands (1), Migrations (2), Factories (1), Seeders (1), Routes (1)
- **Blade/CSS (22):** Member views (20), Components (3), CSS (2)
- **Tests (18):** Feature (17), Unit (1)

---

## Findings

### Critical

| # | Area | Finding |
|---|------|---------|
| C1 | Duplication | **Double authorization on member API path.** `MemberController` calls `authorizeApi()` (Gate + Sanctum), then the action calls `Gate::authorize()` again. CLAUDE.md says "Always use `Gate::authorize()` inside actions, not in controllers" — controller should check only Sanctum ability, not Gate. Files: `MemberController.php:102,121,139` + `CreateMember.php:16`, `UpdateMember.php:16` |
| C2 | Tests | **No tests for `SearchController`.** Command palette search has non-trivial logic (min query length, ILIKE, initials, response shape) and is completely untested. File: `app/Http/Controllers/Web/SearchController.php` |
| C3 | Docs | **No API docs page for Members endpoint.** `documentation.json` has no Members API reference — the first CRUD resource has no dedicated docs. |
| C4 | Components | **Missing `s-btn` base class on ghost buttons.** `inline-actions.blade.php` and several member sub-pages use `s-btn-ghost s-btn-xs` without the required `s-btn` base class. |
| C5 | Components | **Header search uses `docs-search` prefix.** Non-standard `docs-` prefix in `header.blade.php:103-108` instead of `s-search` or unprefixed global layout classes. |

### Important

| # | Area | Finding |
|---|------|---------|
| I1 | Duplication | **Duplicated validation rules between DTO attributes and `rules()` method.** `CreateMemberData` has `#[Required, Max(255)]` attributes AND a `rules()` method with the same rules. Attributes on `$name` and `$membership_type` are redundant. File: `CreateMemberData.php:15-17` vs `57-59` |
| I2 | Duplication | **Near-identical `rules()` in Create/Update DTOs.** 30+ lines of duplicated rule definitions between `CreateMemberData::rules()` and `UpdateMemberData::rules()` — only `required` vs `sometimes` differs. |
| I3 | Duplication | **Duplicated ListValue query in `CustomFieldValidator`.** `applyListOfValuesRules` and `applyMultiListOfValuesRules` both query and extract `$validIds`/`$validNames` with identical code. File: `CustomFieldValidator.php:204-211` vs `227-234` |
| I4 | Duplication | **Duplicated child/parent member mapping in `MemberData::fromModel()`.** Lines 104-119 and 124-139 build the same array shape, differing only by relation name. |
| I5 | Type Safety | **`UpdateMemberData::$membership_type` is `?string` instead of `?MembershipType` enum.** Create DTO uses the enum correctly; update DTO allows any string, bypassing enum validation. File: `UpdateMemberData.php:11` |
| I6 | Money | **`Member::formatMoneyCost()` hardcodes exponent=2.** Ignores currency — JPY (0), BHD (3) would format incorrectly. Should respect `default_currency_code`. File: `Member.php:288-293` |
| I7 | Docs | **Member docs stale after 3-column redesign.** `docs/platform/members.md` still describes old tab layout, missing 7 new tabs (quotes, opportunities, movements, invoices, information, contacts, activities). |
| I8 | Docs | **Command palette undocumented.** Cmd+K / Ctrl+K keyboard shortcut and `/search` route have no documentation. |
| I9 | Docs | **`MemberController` lacks PHPDoc for Scramble.** No `@param` tags for query parameters, no `@response` tags. Scramble can't document filters, sorts, or includes. |
| I10 | Tests | **No authorization tests for `UpdateCustomField` and `DeleteCustomField`.** Both use `Gate::authorize('custom-fields.manage')` but tests only verify auth for create. |
| I11 | Tests | **No test for `CustomFieldDefinitionResolver::resolve()` core logic.** Tests only cover cache clearing, not the actual resolution/filtering of active definitions. |
| I12 | Components | **Inline styles duplicating `s-card` pattern.** Health score card in `show.blade.php:425-435` uses raw inline styles matching `.s-card` + `.s-card-body`. |
| I13 | Components | **AI Recommendations use inline styles instead of `s-alert` variants.** `show.blade.php:202-226` — three cards with colored borders duplicate `s-alert-danger/warning/info`. |
| I14 | Components | **Raw `div` separator instead of `s-dropdown-sep`.** `member-header.blade.php:64` duplicates the existing class. |
| I15 | Components | **Custom Fields heading uses inline styles instead of `s-panel-title`.** `form.blade.php:423` — exact match for existing component pattern. |
| I16 | Components | **Pagination active button uses inline styles.** `show.blade.php:406` — should use `s-pagination-btn active` class. |

### Suggestions

| # | Area | Finding |
|---|------|---------|
| S1 | Duplication | Initials logic duplicated between `SearchController:30-33` and `User::initials()`. Consider `Member::initials()` accessor. |
| S2 | Duplication | `MemberData::formatTimestamp()` is private but will be needed by other response DTOs. Extract to shared trait. |
| S3 | Duplication | `InviteUser` and `BackfillUserMembers` create members directly instead of using `CreateMember` action — skips custom field defaults, audit, webhooks. |
| S4 | Duplication | `array_filter($data->toArray(), fn ($v) => $v !== null)` appears in many update actions. Could be a base DTO method. |
| S5 | Docs | `MemberData::fromModel()` lacks PHPDoc despite being 195 lines. |
| S6 | Docs | `signals:backfill-user-members` artisan command is undocumented. |
| S7 | Components | `s-badge-zinc` used in 4 places but not defined in `components.css`. |
| S8 | Tests | No test for webhook dispatch in member create/update actions. |
| S9 | Tests | No explicit test for `UpdateMember` with `null` custom_fields path. |
| S10 | Tests | No validation failure tests for custom field actions through the action layer. |

---

## Documentation Status

| Layer | Status | Details |
|-------|--------|---------|
| User docs | needs-update | Members page stale (old layout), command palette missing, backfill command missing |
| API docs (Scramble) | needs-update | MemberController lacks @param/@response PHPDoc; no Members API docs page |
| Code PHPDoc | mostly-compliant | `MemberData::fromModel()` missing PHPDoc; `UpdateMemberData` type mismatch |

## Component Library Compliance

2 critical, 5 important issues. Main themes: missing `s-btn` base class on ghost buttons, `docs-search` legacy prefix, and ~6 places using inline styles that duplicate existing `s-*` components (`s-card`, `s-alert`, `s-dropdown-sep`, `s-panel-title`, `s-pagination-btn`). Flux and x-signals components used correctly throughout forms.

## Test Coverage

| Area | Status | Gap |
|------|--------|-----|
| SearchController | **missing** | Zero test coverage — needs full test file |
| MemberController API | covered | Excellent — CRMS compat, filters, includes, CRUD |
| Member Actions | covered | Happy path + auth + custom fields |
| Custom Field Actions | partial | Missing auth tests for update/delete |
| CustomFieldDefinitionResolver | partial | Only cache tests, no resolve() logic test |
| CustomFieldValidator | covered | All 19 field types, required/nullable, edge cases |
| CustomFieldSerializer | covered | Comprehensive — serialization, defaults, eager loading |
| RansackFilter | covered | All 18 predicates, security, edge cases |
| BackfillUserMembers | covered | Create, skip, active flag |
| Livewire Members | covered | Index, Show, Form, Tabs |

## Agent Reviews Dispatched

### pr-review-toolkit:code-reviewer
Key findings (3 critical, 3 important):
- **C6** Commerce fields (`peppol_id`, `chamber_of_commerce_number`, `global_location_number`) added to model but missing from Create/Update DTOs — write-inaccessible via API
- **C7** `SearchController` ILIKE wildcards not escaped (same as silent-failure F7)
- **C8** `UpdateMemberData::$membership_type` typed as `?string` not enum (confirms I5)
- **I17** `SearchController` lacks `members.view` permission check — any authenticated user can search members
- **I18** `SearchController` doesn't extend base controller — no `$this->authorize()` available
- **I19** Migration filename references "uuid" but no UUID column is added

### pr-review-toolkit:silent-failure-hunter
10 findings (1 critical, 3 high, 6 medium):
- **C9** `BackfillUserMembers` has no transaction wrapping — partial backfill leaves inconsistent state (Member created but User not updated = orphan)
- **I20** `UpdateMember` passes `custom_fields` key to `$member->update()` — silently discarded by `$fillable` guard (works by accident)
- **I21** `CustomFieldSerializer::fromArray()` silently drops unknown field names without logging
- **I22** ListValue name resolution silently stores `null` when name doesn't match — user data lost
- **I23** `MemberData::fromModel()` `$related->pivot->id ?? $related->id` masks missing pivot data with wrong entity ID
- **I24** `UpdateCustomField` uses `$field->fresh()` which can return `null` — should use `$field->refresh()`
- S11: `RansackFilter::applyPredicate` `default => null` silently ignores future predicates
- S12: Custom field filters silently ignore non-searchable/missing fields without logging
- S13: Orphaned custom_field_values silently skipped without logging

### pr-review-toolkit:type-design-analyzer
DTO ratings and cross-DTO consistency:
- `CreateMemberData`: Encapsulation 6/10, Invariant Expression 4/10 — no membership-type-conditional validation, dual validation declaration
- `UpdateMemberData`: Encapsulation 5/10, Invariant Expression 3/10 — `?string` membership_type, cannot null-out fields via `array_filter` pattern
- `MemberData`: Invariant Usefulness 8/10, Enforcement 7/10 — well-structured response DTO, nested structures untyped
- Key cross-DTO gap: `membership_type` is enum in Create, raw string in Update, label string in Response — three different representations

## Resolution Status
Awaiting user direction on which findings to address.
