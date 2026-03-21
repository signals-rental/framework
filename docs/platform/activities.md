---
title: Activities
description: Track tasks, calls, meetings, emails, and follow-ups against members and opportunities.
---

## Overview

Activities represent CRM engagement items in Signals. Each activity has a type, status, priority, and can be linked to a member or opportunity via the "regarding" association. Activities help teams track follow-ups, schedule calls, log meetings, and manage tasks.

| Type | Description |
|------|-------------|
| Task | General to-do items and action points |
| Call | Phone calls — scheduled or logged |
| Meeting | In-person or virtual meetings |
| Email | Email correspondence records |
| Note | Free-form notes and observations |

## Activities List

**Route:** `/activities`

Browse all activities with search, type filtering, sorting, and status views.

- **Search** — filter by subject (debounced, case-insensitive)
- **Status filter** — switch between Scheduled, Completed, and All
- **Type filter** — filter by activity type
- **Column sorting** — click column headers to sort
- **Pagination** — configurable items per page

| Column | Description |
|--------|-------------|
| Subject | Activity title or summary |
| Type | Task, Call, Meeting, Email, or Note |
| Status | Current status (e.g. Scheduled, Completed) |
| Priority | Priority level |
| Regarding | Linked member or opportunity |
| Owner | Assigned user |
| Starts At | Scheduled start date/time |
| Created | When the activity was created |

## Activity Detail

**Route:** `/activities/{id}`

View full activity details including description, linked entities, and audit history.

## Creating Activities

**Route:** `/activities/create`

Create a new activity by specifying subject, type, status, priority, owner, and optional regarding association.

## Editing Activities

**Route:** `/activities/{id}/edit`

Edit an existing activity using the same form as create.

### Form Fields

| Field | Description |
|-------|-------------|
| Subject | Activity title or summary (required) |
| Description | Detailed notes or body text |
| Location | Where the activity takes place |
| Type | Activity type (Task, Call, Meeting, Email, Note) |
| Status | Current status |
| Priority | Priority level |
| Time Status | Free, Busy, or Tentative |
| Owner | Assigned user |
| Regarding Type | Entity type the activity relates to (Member, Opportunity, etc.) |
| Regarding ID | ID of the related entity |
| Starts At | Scheduled start date/time |
| Ends At | Scheduled end date/time |

## Permissions

| Permission | Description |
|------------|-------------|
| `activities.access` | Access the activities module |
| `activities.view` | View activity details |
| `activities.create` | Create new activities |
| `activities.edit` | Edit existing activities |
| `activities.delete` | Delete activities |
| `activities.complete` | Mark activities as complete |

## Scoped Activity Tabs

Activities can be viewed in context on related entity detail pages:

| Entity | Tab Location | Description |
|--------|-------------|-------------|
| Member | `/members/{id}` Activities tab | Activities linked to this member |
| Product | `/products/{id}` Activities tab | Activities linked to this product |
| Stock Level | `/stock-levels/{id}` Activities tab | Activities linked to this stock level |
