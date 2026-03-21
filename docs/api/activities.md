# Activities API

CRUD endpoints for managing activities (tasks, calls, meetings, emails, notes).

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/activities` | List activities |
| GET | `/api/v1/activities/{id}` | Show an activity |
| POST | `/api/v1/activities` | Create an activity |
| PUT | `/api/v1/activities/{id}` | Update an activity |
| DELETE | `/api/v1/activities/{id}` | Delete an activity |

## Authentication

Requires a Sanctum bearer token with `activities:read` (GET) or `activities:write` (POST/PUT/DELETE) ability.

## List Activities

```
GET /api/v1/activities
```

### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | integer | Page number (default: 1) |
| `per_page` | integer | Items per page (default: 20, max: 100) |
| `q[subject_cont]` | string | Filter by subject containing value |
| `q[type_id_eq]` | integer | Filter by activity type |
| `q[status_id_eq]` | integer | Filter by status |
| `q[completed_true]` | boolean | Filter completed activities |
| `include` | string | Comma-separated relationships to include |

### Response

```json
{
    "activities": [
        {
            "id": 1,
            "subject": "Follow up on quote",
            "type_id": 1001,
            "status_id": 2001,
            "priority": 1,
            "description": "Call to discuss pricing",
            "regarding_type": "Member",
            "regarding_id": 42,
            "owner_id": 5,
            "starts_at": "2026-03-22T10:00:00Z",
            "ends_at": "2026-03-22T10:30:00Z",
            "completed": false,
            "completed_at": null,
            "custom_fields": {},
            "created_at": "2026-03-21T09:00:00Z",
            "updated_at": "2026-03-21T09:00:00Z"
        }
    ],
    "meta": {
        "total": 1,
        "per_page": 20,
        "page": 1
    }
}
```

## Show Activity

```
GET /api/v1/activities/{id}
```

### Response

```json
{
    "activity": {
        "id": 1,
        "subject": "Follow up on quote",
        "type_id": 1001,
        "status_id": 2001,
        "priority": 1,
        "description": "Call to discuss pricing",
        "regarding_type": "Member",
        "regarding_id": 42,
        "owner_id": 5,
        "starts_at": "2026-03-22T10:00:00Z",
        "ends_at": "2026-03-22T10:30:00Z",
        "completed": false,
        "completed_at": null,
        "custom_fields": {},
        "created_at": "2026-03-21T09:00:00Z",
        "updated_at": "2026-03-21T09:00:00Z"
    }
}
```

## Create Activity

```
POST /api/v1/activities
```

### Request Body

```json
{
    "activity": {
        "subject": "Follow up on quote",
        "type_id": 1001,
        "status_id": 2001,
        "priority": 1,
        "description": "Call to discuss pricing",
        "regarding_type": "Member",
        "regarding_id": 42,
        "owner_id": 5,
        "starts_at": "2026-03-22T10:00:00Z",
        "ends_at": "2026-03-22T10:30:00Z"
    }
}
```

## Update Activity

```
PUT /api/v1/activities/{id}
```

Accepts the same body as create. Only include fields to update.

## Delete Activity

```
DELETE /api/v1/activities/{id}
```

Returns `204 No Content` on success.
