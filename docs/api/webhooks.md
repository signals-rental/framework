---
title: Webhooks
description: Subscribe to real-time event notifications delivered to your server via HTTPS.
---

## Overview

Webhooks let you receive real-time notifications when events happen in Signals. Register an endpoint URL, choose which events to subscribe to, and Signals will send a signed HTTP POST to your server whenever those events occur.

## Managing Webhooks

Webhooks can be managed via the API (`/api/v1/webhooks`) or from the admin panel at **Admin > Settings > Webhooks**.

## Events

### Members

| Event | Trigger |
|-------|---------|
| `member.created` | A new member is created |
| `member.updated` | A member's details are updated |
| `member.archived` | A member is archived |
| `member.restored` | An archived member is restored |
| `member.deleted` | A member is permanently deleted |
| `member.merged` | Two members are merged (secondary archived into primary) |
| `member.anonymised` | A member's personally identifiable information is erased |

### Products

| Event | Trigger |
|-------|---------|
| `product.created` | A new product is created |
| `product.updated` | A product's details are updated |
| `product.archived` | A product is archived (soft-deleted, recoverable) |
| `product.restored` | An archived product is restored |
| `product.deleted` | A product is deleted |
| `product.merged` | Two products are merged |
| `product_group.created` | A new product group is created |
| `product_group.updated` | A product group is updated |
| `product_group.deleted` | A product group is deleted |

### Stock

| Event | Trigger |
|-------|---------|
| `stock_level.created` | A stock level record is created |
| `stock_level.updated` | A stock level is updated |
| `stock_level.deleted` | A stock level is deleted |
| `stock_transaction.created` | A stock transaction is recorded |
| `stock_transaction.deleted` | A stock transaction is deleted |

### Activities

| Event | Trigger |
|-------|---------|
| `activity.created` | A new activity is created |
| `activity.updated` | An activity is updated |
| `activity.deleted` | An activity is deleted |
| `activity.completed` | An activity is marked as completed |

### Rate Definitions

| Event | Trigger |
|-------|---------|
| `rate_definition.created` | A rate definition is created |
| `rate_definition.updated` | A rate definition is updated |
| `rate_definition.deleted` | A rate definition is deleted |
| `product_rate.created` | A product rate is assigned |
| `product_rate.updated` | A product rate is updated |
| `product_rate.deleted` | A product rate is removed |

### Tax

| Event | Trigger |
|-------|---------|
| `tax_rate.created` | A tax rate is created |
| `tax_rate.updated` | A tax rate is updated |
| `tax_rate.deleted` | A tax rate is deleted |
| `tax_rule.created` | A tax rule is created |
| `tax_rule.updated` | A tax rule is updated |
| `tax_rule.deleted` | A tax rule is deleted |

### Users

| Event | Trigger |
|-------|---------|
| `user.created` | A new user is invited or created |
| `user.updated` | A user's profile or roles are updated |
| `user.deactivated` | A user account is deactivated |
| `user.deleted` | A user is deleted |

### Roles

| Event | Trigger |
|-------|---------|
| `role.created` | A new role is created |
| `role.updated` | A role's name or permissions are modified |
| `role.deleted` | A role is deleted |

### Settings

| Event | Trigger |
|-------|---------|
| `settings.updated` | Application settings are changed |

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

## Payload Schemas

Most events embed the affected resource under a singular key inside `data`, using the same shape the REST API returns. Lifecycle events that only need to identify a record (deletions, archive/restore) send just an `id`.

### Tax Rate Events

`tax_rate.created` and `tax_rate.updated` include the full tax rate object:

```json
{
    "event": "tax_rate.created",
    "timestamp": "2026-01-15T14:30:00Z",
    "data": {
        "tax_rate": {
            "id": 1,
            "name": "UK Standard",
            "description": "Standard rate VAT",
            "rate": "20.0000",
            "is_active": true,
            "created_at": "2026-01-15T14:30:00Z",
            "updated_at": "2026-01-15T14:30:00Z"
        }
    }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Tax rate identifier |
| `name` | string | Display name (e.g. `UK Standard`) |
| `description` | string\|null | Optional description |
| `rate` | string | Percentage as a decimal string (e.g. `20.0000`) |
| `is_active` | boolean | Whether the rate is active |
| `created_at` / `updated_at` | string | ISO 8601 UTC timestamps |

`tax_rate.deleted` sends only the identifier:

```json
{
    "event": "tax_rate.deleted",
    "timestamp": "2026-01-15T14:30:00Z",
    "data": { "id": 1 }
}
```

### Tax Rule Events

`tax_rule.created` and `tax_rule.updated` include the full tax rule object:

```json
{
    "event": "tax_rule.created",
    "timestamp": "2026-01-15T14:30:00Z",
    "data": {
        "tax_rule": {
            "id": 5,
            "organisation_tax_class_id": 1,
            "product_tax_class_id": 2,
            "tax_rate_id": 1,
            "priority": 10,
            "is_active": true,
            "created_at": "2026-01-15T14:30:00Z",
            "updated_at": "2026-01-15T14:30:00Z"
        }
    }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Tax rule identifier |
| `organisation_tax_class_id` | integer | Organisation tax class matched by the rule |
| `product_tax_class_id` | integer | Product tax class matched by the rule |
| `tax_rate_id` | integer | Tax rate applied when the rule matches |
| `priority` | integer | Higher priority rules win when several match |
| `is_active` | boolean | Whether the rule is active |
| `created_at` / `updated_at` | string | ISO 8601 UTC timestamps |

`tax_rule.deleted` sends only the identifier:

```json
{
    "event": "tax_rule.deleted",
    "timestamp": "2026-01-15T14:30:00Z",
    "data": { "id": 5 }
}
```

### Product Archive & Restore Events

`product.archived` and `product.restored` are lifecycle transitions on an existing product and carry only its identifier. Fetch the current state from `GET /api/v1/products/{id}` if you need the full record.

```json
{
    "event": "product.archived",
    "timestamp": "2026-01-15T14:30:00Z",
    "data": { "id": 42 }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Product identifier |

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
