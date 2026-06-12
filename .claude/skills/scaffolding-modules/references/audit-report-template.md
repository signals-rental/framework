# Audit Report Template (`existing` mode)

Copy this structure when auditing an existing module. Save to `.claude/reviews/module-audit-{module}-{YYYY-MM-DD}.md`. The members audit (`.claude/reviews/module-audit-members-2026-06-12.md`) is the worked reference — match its shape.

Grade every checklist item against the codebase as **present** (cite the file path / grep anchor as evidence), **partial** (state exactly what exists and what is missing), or **missing**. Evidence is mandatory for `present` — a bare "present" with no file path is not a grade. Use **N/A** (not "missing") for items that genuinely do not apply, and say why.

---

```markdown
---
audit: {module}
date: {YYYY-MM-DD}
auditor: {model / agent}
skill: scaffolding-modules / existing mode
---

# {Module} Module Audit — {YYYY-MM-DD}

## Summary Table

| Checklist Phase | Present | Partial | Missing |
|-----------------|---------|---------|---------|
| 0. Review gate | … | … | … |
| 1. Data model | | | |
| 2. Actions + DTOs + events | | | |
| 3. API | | | |
| 4. List-sync registrations | | | |
| 5. List page | | | |
| 6. Record (show) page | | | |
| 7. Form page | | | |
| 8. Admin panels | | | |
| 9. Permissions | | | |
| 10. Search & navigation | | | |
| 11. Docs | | | |
| 12. Tests + quality gate | | | |
| Cross-cutting | | | |
| **TOTAL** | | | |

---

## Graded Checklist

### 1. Data model
- **present** — {what} (`path/to/file.php`)
- **partial** — {what exists} / {what is missing}
- **missing** — {item} ({why it matters})

### 2. Actions, DTOs, events
…

(one subsection per checklist phase, 1–12 + Cross-cutting. Verify each list-sync row and admin surface
against `references/members-exemplar.md` by grepping the registration point for the module's keys.)

---

## Prioritised Gap List

### P0 — Security / Data Integrity
**G1. {short title}** — {description, why it's a security/data-integrity risk}.
- File: `path:line-or-grep-anchor`

### P1 — API / UI / Data Contract Gaps
**G_n. {short title}** — {description}.
- Files: `…`

### P2 — Docs / Test Gaps
**G_n. {short title}** — {description}.
- File: `…`

---

## Exemplar Quality Verdict

Only when auditing a module that others will copy from (e.g. Members).

### Safe to copy verbatim
- {patterns proven correct}

### Do NOT copy (module-specific gaps or superseded patterns)
- {anti-patterns the auditor found — e.g. "webhook dispatched inside the transaction; copy the after-commit version instead"}

### Overall verdict
{X}% complete against the checklist. {One-paragraph judgement of whether it is safe to use as an exemplar and with what caveats.}

---

## Decision Log

Record every intentional skip and every plan-vs-code discrepancy found in Phase 0 (silence is not a decision):

| Item | Decision | Rationale |
|------|----------|-----------|
| {e.g. merge} | not applicable | {reference table, no user-created duplicates} |
| {e.g. URL placement} | code uses `/x`, plan said `/resources/x` | flagged to user; code is authoritative |
```

---

## Grading guidance

- **P0 first, always.** Nav/palette links that leak feature visibility past a permission gate, `exists:` rules that accept soft-deleted records, webhooks dispatched inside a transaction — these are P0 even when "the page still 403s".
- **P1** = API/UI contract: missing webhook dispatch or registration, missing REST endpoint for an action that exists, a dashboard quick action linking to `#`, missing custom-view validation.
- **P2** = docs drift and test gaps: stale platform docs, undocumented webhook events, missing `action_logs` assertions, incomplete role matrix.
- A list-sync row is **missing** if the registration point does not contain the module's key — grep it, don't assume.
- For an exemplar audit, the verdict section is the highest-value output: it tells the next scaffolder which patterns to copy and which to avoid.
