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
| POST | `/api/v1/activities/{id}/complete` | Mark activity as complete |

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
            "subject": "Follow up on rental quote",
            "description": null,
            "location": null,
            "regarding_id": 42,
            "regarding_type": "Member",
            "owned_by": 1,
            "starts_at": "2026-03-21T10:00:00.000Z",
            "ends_at": "2026-03-21T10:30:00.000Z",
            "priority": 1,
            "type_id": 1001,
            "status_id": 2001,
            "completed": false,
            "time_status": 0,
            "custom_fields": {},
            "participants": [],
            "activity_type_name": "Task",
            "activity_status_name": "Scheduled",
            "time_status_name": "Free",
            "created_at": "2026-03-21T10:00:00.000Z",
            "updated_at": "2026-03-21T10:00:00.000Z"
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
        "subject": "Follow up on rental quote",
        "description": null,
        "location": null,
        "regarding_id": 42,
        "regarding_type": "Member",
        "owned_by": 1,
        "starts_at": "2026-03-21T10:00:00.000Z",
        "ends_at": "2026-03-21T10:30:00.000Z",
        "priority": 1,
        "type_id": 1001,
        "status_id": 2001,
        "completed": false,
        "time_status": 0,
        "custom_fields": {},
        "participants": [],
        "activity_type_name": "Task",
        "activity_status_name": "Scheduled",
        "time_status_name": "Free",
        "created_at": "2026-03-21T10:00:00.000Z",
        "updated_at": "2026-03-21T10:00:00.000Z"
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
        "owned_by": 5,
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

## Complete Activity

```
POST /api/v1/activities/{id}/complete
```

Marks an activity as complete. Sets `completed` to `true` and updates the status accordingly.

### Response

```json
{
    "activity": {
        "id": 1,
        "subject": "Follow up on rental quote",
        "description": null,
        "location": null,
        "regarding_id": 42,
        "regarding_type": "Member",
        "owned_by": 1,
        "starts_at": "2026-03-21T10:00:00.000Z",
        "ends_at": "2026-03-21T10:30:00.000Z",
        "priority": 1,
        "type_id": 1001,
        "status_id": 2001,
        "completed": true,
        "time_status": 0,
        "custom_fields": {},
        "participants": [],
        "activity_type_name": "Task",
        "activity_status_name": "Scheduled",
        "time_status_name": "Free",
        "created_at": "2026-03-21T10:00:00.000Z",
        "updated_at": "2026-03-21T10:00:00.000Z"
    }
}
```
