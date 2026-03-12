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

Browse all members with search, type filtering, sorting, and bulk operations. The list is powered by the reusable DataTable component.

- **Search** — filter by name (debounced, case-insensitive)
- **Type filter** — chip buttons to show All, Contacts, Organisations, or Venues with counts
- **Column sorting** — click column headers to sort by Name, Type, Status, or Created date
- **Column filtering** — inline filters on Type (select) and Status (select)
- **Pagination** — 12 members per page (configurable: 12, 24, 48)
- **Row selection** — checkbox selection with shift-click range support
- **Bulk actions** — delete multiple selected members at once

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

Each row has a dropdown menu with View, Edit, and Delete actions. Deleting a member shows a confirmation modal and soft-deletes the record.

### Bulk Actions

Select multiple members using checkboxes, then use the bulk action bar to delete selected members. A confirmation modal appears before deletion.

## Member Detail

**Route:** `/members/{member}`

The detail page shows a header with the member's name, type badge, and status badge, followed by tab navigation for managing related data.

### Overview Tab

The overview tab displays two sections:

- **Details** — name, type, description, default currency, locale, and organisation tax class (when set)
- **Summary** — counts of addresses, emails, phones, and links, plus linked organisations or contacts with clickable links

### Tabs

| Tab | Route | Description |
|-----|-------|-------------|
| Overview | `/members/{member}` | Details, summary counts, linked members |
| Addresses | `/members/{member}/addresses` | Physical addresses |
| Emails | `/members/{member}/emails` | Email addresses |
| Phones | `/members/{member}/phones` | Phone numbers |
| Links | `/members/{member}/links` | Web links and social profiles |
| Custom Fields | `/members/{member}/custom-fields` | Custom field values grouped by field group |
| Relationships | `/members/{member}/relationships` | Links between contacts and organisations |

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

## Permissions

| Permission | Description |
|------------|-------------|
| `members.create` | Create new members |
| `members.edit` | Edit existing members |
| `members.delete` | Delete members |
| `members.view` | View member details |
