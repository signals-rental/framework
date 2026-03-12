# Members UI & Reusable Data Table — Design

**Date:** 2026-03-11
**Status:** Approved

## Summary

Five changes to the members frontend:

1. **CRM mega dropdown** in the global header nav (matching Operations pattern)
2. **URL-based routing** — links go to `/members` not `/api/v1/members`
3. **Sticky footer** on members pages (and all pages)
4. **Reusable data table** Livewire component with sorting, filtering, checkboxes, search
5. **Row actions menu** — ellipsis dropdown with View, Edit, Delete, extensible slots

## 1. CRM Mega Dropdown

Replace the direct "CRM" link with a mega dropdown matching the Operations dropdown pattern.

### Structure

```
CRM (dropdown)
├── People & Places (group label)
│   ├── Members        → /members           (All members directory)
│   ├── Organisations  → /members?type=organisation
│   ├── Venues         → /members?type=venue
│   └── Contacts       → /members?type=contact
└── Engagement (group label)
    ├── Activities     → # (placeholder)
    └── Projects       → # (placeholder)
```

Active state: `request()->routeIs('members.*')` on the CRM dropdown button.

Mobile sidebar CRM section gets matching sub-items (Organisations, Venues, Contacts indented under Members).

## 2. Reusable Data Table Component

### Component: `App\Livewire\Components\DataTable`

A Livewire component that provides interactive table features out of the box.

### Column Definition

```php
$columns = [
    ['key' => 'checkbox', 'type' => 'checkbox'],
    ['key' => 'name', 'label' => 'Name', 'sortable' => true, 'filterable' => true, 'filter_type' => 'text'],
    ['key' => 'type', 'label' => 'Type', 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => [...]],
    ['key' => 'actions', 'type' => 'actions'],
];
```

### Features

- **Search bar** — global text search in table header toolbar
- **Sortable columns** — click header to toggle asc/desc with visual indicator
- **Column filter popovers** — click filter icon in header for text/select/date filter
- **Checkbox column** — select-all/individual with bulk action bar
- **Actions column** — ellipsis dropdown menu (View, Edit, Delete + extensible slots)
- **Pagination** — Laravel pagination below table
- **Empty state** — configurable message
- **Loading states** — wire:loading overlay during operations

### Bulk Action Bar

Appears when rows are selected. Default actions: Delete, Export. Extensible via slot.

### Row Actions Menu

Ellipsis button opens dropdown:
- View → show page
- Edit → edit page
- (divider)
- Delete → with wire:confirm
- (extensible slot for future actions)

## 3. Sticky Footer

Add `<x-layouts.app.footer>` inside `.app-main` after `{{ $slot }}`. Already styled with `position: sticky; bottom: 0`.

## 4. URL-based Type Filter

Members index reads `?type=` query param to pre-set `typeFilter`. Chip filter bar stays in sync. Changing chips updates URL via query string.

## Files Affected

- `resources/views/components/layouts/app/header.blade.php` — CRM dropdown + footer
- `app/Livewire/Components/DataTable.php` — new reusable component
- `resources/views/livewire/components/data-table.blade.php` — table template
- `resources/views/livewire/members/index.blade.php` — use DataTable component
- `resources/css/app.css` — data table styles (s-data-table-*)
- Tests for DataTable component
