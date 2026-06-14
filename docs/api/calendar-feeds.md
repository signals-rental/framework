# Calendar Feeds API

Secure iCalendar (`.ics`) feeds that publish scheduled activities for subscription in Google Calendar, Apple Calendar, Outlook, and any other RFC 5545–compatible client.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/calendar/feed.ics` | Global feed — every scheduled activity in the system |
| GET | `/calendar/feed/{user}.ics` | Per-user feed — only activities owned by `{user}` |

Both endpoints return `Content-Type: text/calendar; charset=utf-8` with a `Content-Disposition: inline; filename="signals-calendar.ics"` header.

## Authentication

These feeds are **not** authenticated with a Sanctum bearer token. Calendar clients cannot send custom headers when polling a subscription, so the feeds are protected with Laravel **signed URLs** instead. Each URL carries a `signature` query parameter that is verified by the `signed` middleware on every request.

- A valid signature returns `200` with the iCalendar body.
- A missing or tampered signature returns `403 Forbidden`.
- The request is served **without** a logged-in session — the signature is the only credential.

Signed URLs have **no expiry**, so a subscribed client keeps polling indefinitely.

## Feed Window

Both feeds include activities whose start time falls within the window:

```
starts_at >= now() - 1 year   (no upper bound)
```

That is, everything from one year ago up to any point in the future. Activities with no start time are excluded — they are not schedulable and never appear in a feed. The per-user feed additionally filters to activities owned by the requested user.

## Feed Content

The body is a single `VCALENDAR` (`VERSION:2.0`, `CALSCALE:GREGORIAN`) containing one `VEVENT` per scheduled activity.

| Field | Source |
|-------|--------|
| `UID` | `activity-{id}@{host}` — stable per activity |
| `DTSTAMP` | Generation time (UTC) |
| `DTSTART` / `DTEND` | Activity start/end in UTC (`Ymd\THis\Z`). All-day activities use `;VALUE=DATE` with a date-only value. For all-day events, `DTEND` is the RFC 5545 exclusive end date: a single-day all-day event emits `DTEND` as start + 1 day; a multi-day all-day event emits `DTEND` as the actual exclusive end date, preserving the full span. |
| `SUMMARY` | Activity subject |
| `LOCATION` | Activity location |
| `DESCRIPTION` | Activity description |
| `STATUS` | `CONFIRMED` for Scheduled, Held, and Completed activities (Completed also carries `X-COMPLETED:TRUE`); `CANCELLED` for cancelled activities |
| `TRANSP` | `TRANSPARENT` when the activity's time status is Free; `OPAQUE` when Busy |
| `ORGANIZER` | Owner's email as a `mailto:` CAL-ADDRESS; display name in the double-quoted `CN` parameter (e.g. `ORGANIZER;CN="Jane Operator":mailto:jane@example.com`). Omitted when the owner has no email address. |

Text values are escaped per RFC 5545 (commas, semicolons, backslashes, newlines), and long lines are folded at 75 octets.

### Example

```
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Signals//Calendar//EN
CALSCALE:GREGORIAN
X-WR-CALNAME:Signals Calendar
BEGIN:VEVENT
UID:activity-42@signals.test
DTSTAMP:20260613T090000Z
DTSTART:20260615T090000Z
DTEND:20260615T093000Z
SUMMARY:Follow up on rental quote
LOCATION:Phone
DESCRIPTION:Call to discuss pricing
STATUS:CONFIRMED
TRANSP:OPAQUE
ORGANIZER;CN="Jane Operator":mailto:jane@example.com
END:VEVENT
END:VCALENDAR
```

## Minting & Subscribing

Feed URLs are generated as signed routes:

- Global feed — `URL::signedRoute('calendar.feed.global')`
- Per-user feed — `URL::signedRoute('calendar.feed.user', ['user' => $userId])`

In the application, copyable subscribe URLs are surfaced in two places:

- The **feed modal** on the [Calendar page](/docs/platform/calendar) — every user sees their own per-user feed URL; administrators and owners additionally see the global feed URL and the full per-user list for all staff.
- **Settings → Calendar** (`/settings/calendar`) — the current user's subscribe URL with a copy button and a **Download .ics** button; administrators also see the global feed URL.

To subscribe, copy a feed URL and paste it into your calendar client's "subscribe by URL" / "add calendar from URL" option. The client polls the URL on its own schedule and reflects new and changed activities automatically.

## Security Model & Tradeoff

The feeds rely entirely on the unguessable signature embedded in the URL. This keeps subscription frictionless (no token management, no expiry) but carries a deliberate tradeoff:

- **Not individually revocable** — because the signature is derived from the route and the application key rather than a stored per-feed secret, a single leaked feed URL cannot be revoked on its own.
- **Global invalidation only** — rotating the application's `APP_KEY` changes the signing secret and immediately invalidates **every** existing feed URL (all users, global and per-user). Subscribers must then be issued fresh URLs.

Treat feed URLs as sensitive: anyone with a feed URL can read the activities it exposes without logging in.
