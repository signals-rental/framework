---
title: Members
description: Manage contacts, organisations, venues, and user accounts in the CRM.
---

## Overview

Members is the universal entity in Signals — contacts, organisations, venues, and user accounts are all stored as members differentiated by their `membership_type`. This unified approach allows shared contact details, custom fields, and relationships across all entity types.

| Type | Description |
|------|-------------|
| Contact | Individual people (clients, crew, suppliers) |
| Organisation | Companies, businesses, trusts |
| Venue | Physical locations for events |
| User | System users with login credentials |

## Members List

**Route:** `/members`

Browse all members with search, type filtering, archive filtering, sorting, and bulk operations. The list is powered by the reusable DataTable component.

- **Search** — filter by name (debounced, case-insensitive)
- **Type filter** — chip buttons to show All, Organisations, Venues, or Contacts with counts
- **Archive filter** — chip buttons to show Active, Archived, or All members
- **Column sorting** — click column headers to sort by Name, Type, Status, or Created date
- **Column filtering** — inline filters on Name (text), Type (select), and Status (select)
- **Column toggle** — show or hide individual columns via the toolbar toggle button
- **Export** — export the current filtered result set to CSV via the toolbar
- **Pagination** — 12 members per page (configurable: 12, 24, 48)
- **Row selection** — checkbox selection with shift-click range support
- **Bulk actions** — archive selected members, or merge when exactly 2 are selected

| Column | Description |
|--------|-------------|
| Avatar | Member initials avatar |
| Name | Clickable link to member detail page |
| Type | Membership type badge |
| Primary Email | First primary email, or first available |
| Primary Phone | First primary phone, or first available |
| Status | Active or Inactive badge |
| Tags | Associated tags |
| Created | Creation date |

### Row Actions

Each row has a dropdown menu with the following actions:

- **View** — open the member detail page
- **Edit** — open the member edit form
- **Archive** — soft-delete the member with a confirmation modal (can be restored later)
- **Restore** — available when viewing archived members; restores the member to active

### Bulk Actions

Select multiple members using checkboxes, then use the bulk action bar:

- **Archive Selected** — archives all selected members with a confirmation modal
- **Merge** — appears only when exactly 2 members are selected; opens the merge modal

### Merge Modal

Selecting exactly 2 members and clicking **Merge** opens a merge modal. Choose which member to keep as the primary record; the secondary member's relationships, contact details, custom fields, and other associations are transferred to the primary, then the secondary is archived. Both members must have the same `membership_type`.

### Adding Members

The toolbar **Add Member** button opens a dropdown to create an Organisation, Contact, or Venue.

## Member Detail

**Route:** `/members/{member}`

The detail page shows a header with the member's name, type badge, and status badge, followed by tab navigation for managing related data.

### Overview Tab

The overview tab displays a 3-column layout with:

- **Left** — description, quick actions panel, key contacts or organisations panel, account details summary
- **Centre** — stat cards (CLV, active orders, YTD revenue, outstanding balance), AI recommendations, activity timeline
- **Right** — customer health score gauge, health factor progress bars

### Tabs

| Tab | Route | Description |
|-----|-------|-------------|
| Overview | `/members/{member}` | Summary stats, timeline, key contacts |
| Information | `/members/{member}/information` | Addresses, emails, phones, and links |
| Contacts | `/members/{member}/member-contacts` | Linked contacts or organisations |
| Activities | `/members/{member}/activities` | CRM activities regarding this member |
| Opportunities | `/members/{member}/opportunities` | Rental and sales opportunities |
| Movements | `/members/{member}/movements` | Stock movement history |
| Invoices | `/members/{member}/invoices` | Invoices linked to this member |
| Files | `/members/{member}/files` | Attachments and documents |

> **Note:** Additional tabs for custom fields and relationships are accessible as sub-pages via the Information tab routes.

## Creating & Editing Members

**Routes:** `/members/create`, `/members/{member}/edit`

| Field | Description | Required |
|-------|-------------|----------|
| Name | Member's name | Yes |
| Membership Type | Contact, Organisation, Venue, or User | Yes |
| Active | Whether the member is active | No (default: true) |
| Description | Free-text description | No |
| Locale | Language/region code (e.g. en-GB) | No |
| Default Currency | ISO 4217 currency code | No |
| Organisation Tax Class | Tax classification for billing | No |

## Contact Details

Each member can have multiple addresses, emails, phones, and links. These are polymorphic — the same system is shared across members, stores, and future entities.

### Addresses

Each address can have a label (e.g. "Head Office"), street, city, county, postcode, country, type (from the AddressType list), and a primary flag. Only one address can be primary per member.

### Emails

Each email has an address, type (from the EmailType list), and a primary flag.

### Phones

Each phone has a number, type (from the PhoneType list), and a primary flag.

### Links

Each link has a URL, optional display name, and type (from the LinkType list).

## Relationships

Contacts can be linked to organisations (and vice versa) with a relationship type label (e.g. "employee", "contractor") and an optional primary flag. The relationships tab shows all linked members and allows adding new relationships.

## Custom Fields

Custom fields configured for the "Member" module appear on the Custom Fields tab, grouped by their custom field group. Fields display their current value or "No value set" for empty fields.

## Merge

Two members of the same type can be merged into one. The primary (surviving) member retains all its own data; the secondary member's relationships, contact details, custom field values, and other associations are transferred to the primary, then the secondary is archived. Merge is available from the bulk action bar (select exactly 2 members) and via the API.

## Anonymise

A member's personally identifiable information can be erased to comply with data removal requests. Anonymisation replaces the member's name and description with anonymised placeholders, removes the icon, and deletes all linked emails, phones, addresses, and links. This operation is irreversible. Available via the API only; a user cannot anonymise their own member record.

## Permissions

| Permission | Description |
|------------|-------------|
| `members.view` | View member details |
| `members.create` | Create new members |
| `members.edit` | Edit existing members |
| `members.delete` | Delete, archive, merge, and anonymise members |
