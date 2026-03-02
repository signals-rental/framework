---
title: API Overview
description: Signals provides a full REST API with auto-generated OpenAPI documentation and token authentication.
---

## Overview

Signals is **API-first** — every capability available in the web UI is also accessible programmatically through the REST API. The API uses JSON over HTTPS with bearer token authentication via Laravel Sanctum.

## API Documentation

Full interactive API documentation is auto-generated from the codebase using Scramble. It includes every endpoint, request/response schema, and a **Try It** console for testing requests directly in the browser.

**[Open API Documentation](/docs/api)**

## Base URL

All API endpoints are prefixed with `/api/v1/`. Your full base URL is:

```
{your-app-url}/api/v1/
```

## Authentication

API requests are authenticated with **Bearer tokens** issued via Laravel Sanctum. Include the token in the `Authorization` header:

```bash
curl -H "Authorization: Bearer {your-token}" \
     -H "Accept: application/json" \
     {your-app-url}/api/v1/opportunities
```

Tokens are scoped with abilities using the `resource:action` pattern (e.g. `opportunities:read`, `invoices:write`). A request will be rejected if the token lacks the required ability for that endpoint.

## Response Format

Single resources are wrapped in a singular key, collections in a plural key with pagination metadata:

```json
{
    "opportunity": {
        "id": 1,
        "subject": "Summer Festival 2026"
    }
}
```

```json
{
    "opportunities": [
        { "id": 1, "subject": "Summer Festival 2026" }
    ],
    "meta": {
        "total": 47,
        "per_page": 20,
        "page": 1
    }
}
```

## Filtering

Signals supports Ransack-compatible query syntax for filtering collections:

```
?q[subject_cont]=festival&q[status_eq]=confirmed
```

| Predicate | Meaning |
|-----------|---------|
| `_eq` | Equals |
| `_not_eq` | Not equals |
| `_lt` / `_lteq` | Less than / less than or equal |
| `_gt` / `_gteq` | Greater than / greater than or equal |
| `_cont` / `_not_cont` | Contains / does not contain |
| `_start` / `_end` | Starts with / ends with |
| `_null` / `_not_null` | Is null / is not null |
| `_in` / `_not_in` | In list / not in list |
| `_true` / `_false` | Boolean true / false |

## Pagination

Collections use offset-based pagination:

```
?page=2&per_page=20
```

## Includes

Load related resources with the `include` parameter:

```
?include=items,costs
```

## Errors

Errors follow Laravel's standard validation format:

```json
{
    "message": "The subject field is required.",
    "errors": {
        "subject": ["The subject field is required."]
    }
}
```

> **Note:** The API is under active development. Refer to the [interactive API documentation](/docs/api) for the most up-to-date list of available endpoints.
