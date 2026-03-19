# Attachments API

Endpoints for uploading, listing, and deleting file attachments on entities.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/members/{id}/attachments` | List attachments for a member |
| GET | `/api/v1/attachments/{id}` | Show an attachment |
| POST | `/api/v1/attachments` | Upload a new attachment |
| DELETE | `/api/v1/attachments/{id}` | Delete an attachment |

## Authentication

Requires a Sanctum bearer token with `members:read` (GET) or `members:write` (POST/DELETE) ability.

## List Attachments for a Member

```
GET /api/v1/members/{member_id}/attachments
```

### Filters

| Parameter | Description |
|-----------|-------------|
| `q[mime_type_eq]=application/pdf` | Filter by MIME type |
| `q[category_eq]=document` | Filter by category |
| `q[original_name_cont]=invoice` | Filename contains |

### Sort

`sort=created_at`, `sort=original_name`, `sort=file_size`

## Upload Attachment

```
POST /api/v1/attachments
Content-Type: multipart/form-data
```

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `file` | file | Yes | The file to upload (max 20MB) |
| `attachable_type` | string | Yes | Entity type (e.g. "Member") |
| `attachable_id` | integer | Yes | Entity ID |
| `category` | string | No | Category label (max 50 chars) |
| `description` | string | No | Description (max 1000 chars) |

### Response (201)

```json
{
  "attachment": {
    "id": 1,
    "uuid": "a1b2c3d4-...",
    "original_name": "contract.pdf",
    "mime_type": "application/pdf",
    "file_size": 102400,
    "category": "document",
    "description": "Signed rental contract",
    "url": "https://s3.../attachments/uuid.pdf?signature=...",
    "thumb_url": null,
    "uploaded_by": 1,
    "created_at": "2026-01-15T00:00:00+00:00",
    "updated_at": "2026-01-15T00:00:00+00:00"
  }
}
```

URLs are signed temporary URLs valid for 60 minutes.
