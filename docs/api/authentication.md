---
title: Authentication
description: How to authenticate with the Signals API using bearer tokens.
---

## Authentication

The Signals API uses **Bearer tokens** issued via Laravel Sanctum. Generate tokens from the admin panel at **Admin > Settings > API** or programmatically via the API.

## Creating a Token

Navigate to **Admin > Settings > API** in the web interface. Click **Create Token**, name your token, select the abilities it should have, and click **Generate**. The token value is shown once — copy it immediately.

## Using a Token

Include the token in the `Authorization` header of every API request:

```
Authorization: Bearer {your-token}
```

All requests must also include `Accept: application/json`.

## Token Abilities

Tokens are scoped with abilities that control what endpoints they can access:

| Ability | Description |
|---------|-------------|
| `settings:read` | Read settings |
| `settings:write` | Update settings |
| `users:read` | List and view users |
| `users:write` | Create, update, and deactivate users |
| `roles:read` | List and view roles |
| `roles:write` | Create, update, and delete roles |
| `webhooks:manage` | Manage webhook registrations |
| `system:read` | View system health |
| `action-log:read` | View the audit trail |

## Rate Limiting

API requests are rate-limited. Default limits:

| Context | Limit |
|---------|-------|
| Authenticated | 60 requests/minute |
| Unauthenticated | 20 requests/minute |

Rate limit headers are included in every response:

- `X-RateLimit-Limit` — maximum requests per window
- `X-RateLimit-Remaining` — requests remaining
- `Retry-After` — seconds until limit resets (on 429)

Limits are configurable in **Admin > Settings > API**.
