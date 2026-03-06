---
title: Getting Started
description: Learn how to use the Signals component system when building views, plugins, and custom interfaces.
---

## Component System Overview

Signals uses a unified component library with the `s-` class prefix for all UI elements. Components are split into two categories:

- **Blade components** (`<x-signals.*>`) for structural containers with slots (cards, panels, toolbars, tables)
- **CSS-only classes** (`s-*`) for inline elements applied directly to HTML (buttons, badges, chips, status indicators)

All component CSS is defined in `resources/css/components.css` and globally available via `app.css`. You never need to import component styles per-page.

## When to Use Blade Components vs CSS Classes

Use **Blade components** when the element has multiple child slots or complex internal structure:

```html
<x-signals.card title="Recent Activity">
    <x-slot:headerActions>
        <button class="s-btn s-btn-sm s-btn-ghost">View All</button>
    </x-slot:headerActions>
    <p>Card body content here.</p>
</x-signals.card>
```

Use **CSS classes** when you just need styling on a standard HTML element:

```html
<button class="s-btn s-btn-primary">Save</button>
<span class="s-badge s-badge-green">Active</span>
<span class="s-chip on">Filter</span>
```

## Blade Components Reference

| Component | Tag | Key Props | Slots |
|-----------|-----|-----------|-------|
| Page Header | `<x-signals.page-header>` | `title` | `breadcrumbs`, `meta`, `actions` |
| Card | `<x-signals.card>` | `title` | `headerActions`, default |
| Panel | `<x-signals.panel>` | `title` | `headerActions`, default |
| Table Wrap | `<x-signals.table-wrap>` | -- | default |
| Toolbar | `<x-signals.toolbar>` | -- | default, `right` |
| Tabs | `<x-signals.tabs>` | -- | default |
| Dropdown | `<x-signals.dropdown>` | `align` | default |
| Toast | `<x-signals.toast>` | `message` | `icon` |
| Bulk Bar | `<x-signals.bulk-bar>` | `count` | default |
| Empty State | `<x-signals.empty>` | `icon`, `title`, `description` | -- |
| Stat Grid | `<x-signals.stat-grid>` | -- | default |
| Stat Card | `<x-signals.stat-card>` | `label`, `value`, `color` | `icon` |
| Form Section | `<x-signals.form-section>` | `title` | `icon`, `headerActions`, default |
| Collapsible | `<x-signals.collapsible>` | `label`, `open` | default |
| Multi-Pane | `<x-signals.multi-pane>` | -- | `sidebar`, default |
| Tooltip | `<x-signals.tooltip>` | `title` | default |
| Checkbox | `<x-signals.checkbox>` | `checked` | -- |
| Search | `<x-signals.search>` | `placeholder` | -- |
| Avail Bar | `<x-signals.avail>` | `label`, `percent`, `color` | default |
| Product Cell | `<x-signals.product-cell>` | `name`, `sku` | `thumb` |
| Toggle | `<x-signals.toggle>` | `on` | -- |
| Stepper | `<x-signals.stepper>` | -- | default |
| Progress Bar | `<x-signals.progress>` | `label`, `percent` | -- |
| Dropzone | `<x-signals.dropzone>` | `text`, `hint` | `icon` |

## CSS-Only Components Reference

| Component | Class | Modifiers |
|-----------|-------|-----------|
| Button | `.s-btn` | `.s-btn-primary`, `.s-btn-danger`, `.s-btn-ghost`, `.s-btn-sm` |
| Badge | `.s-badge` | `.s-badge-green`, `.s-badge-amber`, `.s-badge-red`, `.s-badge-blue`, `.s-badge-violet` |
| Chip | `.s-chip` | `.on` (active state) |
| Status | `.s-status` | `.s-status-green`, `.s-status-amber`, `.s-status-red`, `.s-status-blue` |
| Tab | `.s-tab` | `.on` (active state), `.s-tab-count` (child span) |
| Table | `.s-table-wrap` + `.s-table` | `.sortable` (th), `.selected` (tr), `.s-col-check`, `.s-cell-link`, `.s-cell-amount`, `.s-cell-mono` |
| Select | `.s-select` | -- |
| Kbd Hint | `.s-kbd-hint` + `.s-kbd` | -- |
| Section Label | `.s-section-label` | -- |
| Conflict | `.s-conflict` | -- |

## Design Tokens

All CSS custom properties are defined in `resources/css/app.css` with automatic light/dark mode support.

### Fonts

| Token | Font | Usage |
|-------|------|-------|
| `var(--font-display)` | Chakra Petch | Titles, labels, buttons, tabs, badges |
| `var(--font-sans)` | System Sans | Body text, table rows, dropdown items |
| `var(--font-mono)` | Martian Mono | IDs, refs, dates, amounts, table headers |

### Colors

| Token | Value | Usage |
|-------|-------|-------|
| `--green` | #059669 | Primary accent, success, active |
| `--blue` | #2563eb | Links, info, processing |
| `--amber` | #d97706 | Warnings, provisional |
| `--red` | #dc2626 | Errors, danger, cancelled |
| `--violet` | #7c3aed | Archived, special |
| `--navy` | #0f172a | Header, tooltips, dark surfaces |

### Semantic Tokens

| Token | Purpose |
|-------|---------|
| `--text-primary` | Primary text |
| `--text-secondary` | Secondary text |
| `--text-muted` | Muted/hint text |
| `--card-bg` | Card background |
| `--card-border` | Card border |
| `--content-bg` | Page background |
| `--s-subtle` | Subtle backgrounds (table headers) |
| `--s-hover` | Hover state backgrounds |

## CSS Files

| File | Contents |
|------|----------|
| `resources/css/app.css` | Brand tokens, layout (header/sidebar/nav/footer), Tailwind config |
| `resources/css/components.css` | All `s-*` component CSS (62 components) |

## Plugin Development

Plugins use Signals components through config-driven rendering. Plugins do not ship their own views or CSS; instead, they register content via the `PluginRegistrar` and the framework renders it using the canonical component library.

When building plugin interfaces:

1. Use `<x-signals.*>` Blade components for layout structures
2. Use `s-*` CSS classes for inline UI elements
3. Never create custom class prefixes -- use the `s-` namespace
4. Never duplicate component CSS in page-level `<style>` blocks
5. Check the [Component Library](/docs/development/library) for live rendered examples of every component

## Class Prefix Convention

All custom component classes use the `s-` prefix (for "signals").
