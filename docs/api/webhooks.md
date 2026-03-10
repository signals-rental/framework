---
title: Webhooks
description: Subscribe to real-time event notifications delivered to your server via HTTPS.
---

## Overview

Webhooks let you receive real-time notifications when events happen in Signals. Register an endpoint URL, choose which events to subscribe to, and Signals will send a signed HTTP POST to your server whenever those events occur.

## Managing Webhooks

Webhooks can be managed via the API (`/api/v1/webhooks`) or from the admin panel at **Admin > Settings > Webhooks**.

## Events

| Event | Trigger |
|-------|---------|
| `user.created` | A new user is invited or created |
| `user.updated` | A user's profile or roles are updated |
| `user.deactivated` | A user account is deactivated |
| `settings.updated` | Application settings are changed |
| `role.created` | A new role is created |
| `role.updated` | A role's name or permissions are modified |
| `role.deleted` | A role is deleted |

## Payload Format

Every webhook delivery sends a JSON POST request with the event name and associated data:

```json
{
    "event": "user.created",
    "timestamp": "2026-01-15T14:30:00Z",
    "data": {
        "user": {
            "id": 42,
            "name": "Jane Smith",
            "email": "jane@example.com"
        }
    }
}
```

## Verifying Signatures

Every delivery includes an `X-Signals-Signature` header containing an HMAC-SHA256 signature of the request body. Verify it against the secret you received when creating the webhook:

```php
$signature = hash_hmac('sha256', $requestBody, $webhookSecret);

if (! hash_equals($signature, $request->header('X-Signals-Signature'))) {
    abort(401, 'Invalid signature');
}
```

## Retry Behaviour

Failed deliveries (non-2xx response or connection timeout) are retried with exponential backoff up to 6 times over approximately 12 hours. After 3 consecutive days of failures, the webhook is automatically disabled.

Re-enable a disabled webhook from the admin panel or by updating it via the API with `is_active: true`.

## Delivery Logs

Every delivery attempt is logged. View logs via the API at `GET /api/v1/webhooks/{id}/logs` or from the admin panel by clicking the logs icon on a webhook row.
