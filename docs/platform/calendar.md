---
title: Calendar
description: Visualise scheduled activities across day, week, and month views with owner filtering and secure iCal feeds.
---

## Overview

The Calendar is a full-page scheduling view over the [Activities](/docs/platform/activities) module. It plots every scheduled activity onto a day, week, or month grid, colour-coded by owner, and lets you add, view, edit, and complete activities directly from the page without losing your place. It does not introduce a new entity — every event is an activity, and all changes flow through the same write path and permissions as the activities list.

**Route:** `/calendar`

Access to the calendar is gated on the `activities.access` permission. Users without it do not see the Calendar in navigation and cannot open the page.

## Views

A view switcher in the toolbar toggles between three layouts. The selected view is remembered in the URL, so a calendar link can be shared with its view, date, and filters intact.

| View | Layout |
|------|--------|
| Day | Staff **columns** (one per owner) against a vertical hour-axis. Events are placed precisely by their start and end time; overlapping events for the same owner sit side by side. Up to 10 staff columns are shown at once; when there are more than 10 active staff, previous/next controls page through them 10 at a time. |
| Week | Seven day-columns against a vertical hour-axis. Events are coloured by owner. This is the default view. |
| Month | A seven-column grid of day-cells. Each cell shows up to three event chips coloured by owner, sorted by time; a `+N more` link opens that day in Day view. |

In Day and Week views, events are positioned on a pixel hour-axis for Fantastical-style precision. The visible hour window defaults to your configured working hours and automatically expands to include any event that starts or ends outside that window, so nothing is ever clipped. Completed and cancelled activities render in a muted (greyed) style.

### All-day events

Activities have no explicit "all-day" flag. An activity is treated as all-day when it starts at `00:00` and either has no end time or ends at `23:59` (or the following midnight). All-day events render in a band above the hour grid in Day and Week views, and as chips in Month view.

## Navigation & Start Date

The toolbar provides movement controls:

- **Today** — jumps the calendar back to the current date.
- **Previous / Next** — step backwards or forwards by one day, week, or month depending on the active view.
- **Date picker** — pick any start date. The calendar defaults to **today** and looks forwards.

The start date drives the visible range: Day shows that single day, Week shows the week containing it, and Month shows the month containing it.

## Owner Filter

Activities are owned by a system user (the owner is the swimlane axis in Day view and the colour axis in Week and Month views). A filter in the toolbar lists active staff users; selecting one or more owners restricts the calendar to their activities. With no selection, all active staff are shown. Owner colours are assigned deterministically per user, so the same person keeps a consistent colour across column headers, event blocks, chips, and avatars.

> **Note:** Filtering is by **owner** (staff) only. Activity participants are clients, not staff, and are not part of the calendar filter.

## Adding, Viewing & Editing Activities

All create, view, edit, complete, and delete actions happen in modals on the calendar page. Each write reuses the existing Activity actions, so the same validation, authorisation, audit logging, and webhook events apply as anywhere else in the system. After any change, the calendar refreshes locally and instantly — no page reload, no websockets.

### Adding an activity

- Click an empty slot in Day or Week view to open the **add** modal pre-filled with the slot's owner and start time.
- Click a day-cell in Month view to open the add modal pre-filled with that date.
- The add modal reuses the standard activity form. On save, the new event appears immediately.

### Viewing an activity

Click any event to open a **detail modal** showing the activity's subject, type, status, owner, time, and location. From the modal you can:

- **Complete** the activity (if you have `activities.complete`).
- **Edit** the activity (opens the edit modal).
- **Delete** the activity (if you have `activities.delete`).
- Open the full **activity page** at `/activities/{id}` via a link.

### Editing an activity

The edit modal uses the same form as create and is reached from the detail modal's **Edit** action. Saving updates the event in place.

## Unscheduled Tray

Activities with no start time cannot be placed on the grid. They are listed in a collapsible **Unscheduled** tray alongside the calendar. Click any item in the tray to open its detail modal, from where it can be edited (for example, to give it a start time) and so moved onto the calendar.

## Settings That Affect the Calendar

The calendar honours several existing preferences and scheduling settings for display behaviour. iCal feed URLs are managed on the dedicated **Settings → Calendar** page (`/settings/calendar`) — described in [Subscribing to the iCal Feed](#subscribing-to-the-ical-feed) below — but there is no separate screen for scheduling-behaviour configuration.

| Setting | Effect |
|---------|--------|
| `preferences.first_day_of_week` | Determines which weekday the Week view (and the week containing the start date) begins on. `0` = Sunday through `6` = Saturday; default is Monday. |
| `scheduling.default_start_time` | Start of the default visible hour window in Day and Week views (default `09:00`). |
| `scheduling.default_end_time` | End of the default visible hour window in Day and Week views (default `17:00`). |
| `scheduling.weekend_availability` | When disabled, weekend day-columns (Day/Week) and day-cells (Month) are shaded as non-working. All seven days still render — no activities are ever hidden. |

The visible hour window always auto-expands beyond the configured working hours if an event falls outside them, so events scheduled before or after working hours remain visible.

## Subscribing to the iCal Feed

The calendar can be subscribed to from any iCalendar-compatible client (Google Calendar, Apple Calendar, Outlook). Two feed types are available:

- **Global feed** — every scheduled activity in the system.
- **Per-user feed** — only the activities owned by a specific user.

Each feed is a secure, signed URL. Activities from one year ago up to any point in the future are included (there is no upper bound).

### From the calendar page

A **feed** button on the calendar opens a modal with copyable subscribe URLs. Every user sees their own per-user feed URL. Administrators and owners additionally see the global feed URL and the full list of per-user feed URLs for all staff.

### From Settings → Calendar

**Route:** `/settings/calendar`

The per-user calendar settings page shows the current user's personal feed:

- A **subscribe URL** with a copy button, for pasting into a calendar client's "subscribe by URL" option.
- A **Download .ics** button that downloads the current snapshot as a file.
- Short instructions for subscribing in Google, Apple, and Outlook calendars.

Administrators also see the global feed URL on this page.

> **Security note:** Feed URLs are secured with signed URLs and have no expiry, so a subscribed client keeps working indefinitely. Because the signature is the only credential, an individual feed URL cannot be revoked on its own. To invalidate **all** feed URLs at once, rotate the application's `APP_KEY`. See the [Calendar Feeds API](/docs/api/calendar-feeds) for the full security model.

## Permissions

The calendar introduces no new permissions. It reuses the existing `activities.*` permissions.

| Permission | Description |
|------------|-------------|
| `activities.access` | Access the calendar page and see it in navigation |
| `activities.view` | View activity details in the detail modal |
| `activities.create` | Add activities from empty slots and day-cells |
| `activities.edit` | Edit activities from the detail modal |
| `activities.delete` | Delete activities from the detail modal |
| `activities.complete` | Mark activities as complete from the detail modal |
