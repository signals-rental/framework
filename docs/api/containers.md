# Containers API

Containers group serialised assets into a single dispatchable unit (kits, hybrid kits, transport boxes). This API exposes the **availability subset** only — listing/reading containers and packing/unpacking items. Packing a kit or hybrid-fixed container holds its contents from individual availability via a container demand; unpacking releases it.

> The broader container lifecycle — seal, dissolve, scan, repack, dispatch, and return — is not part of this API yet. Only `open` containers accept pack/unpack.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/containers` | List containers (filter, sort, paginate) |
| GET | `/api/v1/containers/{container}` | Show a container with its packed contents |
| POST | `/api/v1/containers/{container}/pack` | Pack a serialised item into an open container |
| POST | `/api/v1/containers/{container}/unpack` | Unpack a serialised item from an open container |

## Authentication

Requires a Sanctum bearer token with `containers:read` (GET) or `containers:write` (POST pack/unpack), and the matching `containers.view` / `containers.pack` permission.

## List Containers

```
GET /api/v1/containers
```

Supports Ransack `q[...]` filters (`name`, `barcode`, `status`, `scan_mode`, `is_temporary`, `product_id`, `store_id`, `opportunity_id`, `created_at`, `updated_at`), `sort`, offset pagination (`page`, `per_page`), and `?include=product,store,active_items`.

### Response

```json
{
    "containers": [
        {
            "id": 1,
            "uuid": "9b1c…",
            "name": "Lighting Kit A",
            "barcode": "KIT-A-001",
            "status": "open",
            "scan_mode": "individual",
            "is_temporary": false,
            "serialised_item_id": 88,
            "product_id": 12,
            "parent_container_id": null,
            "store_id": 1,
            "opportunity_id": null,
            "availability_mode": "kit",
            "notes": null,
            "created_at": "2026-06-19T10:00:00.000Z",
            "updated_at": "2026-06-19T10:00:00.000Z"
        }
    ],
    "meta": { "total": 1, "per_page": 20, "page": 1 }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Container ID |
| `uuid` | string | Public UUID |
| `name` | string | Container name |
| `barcode` | string\|null | Optional barcode |
| `status` | string | `open`, `sealed`, … |
| `scan_mode` | string | How the container's contents are scanned |
| `is_temporary` | bool | Temporary (ad-hoc) container |
| `serialised_item_id` | int\|null | The serialised housing asset (null for temporary) |
| `product_id` | int\|null | Backing containerable product |
| `parent_container_id` | int\|null | Parent container when nested |
| `store_id` | int\|null | Holding store |
| `opportunity_id` | int\|null | Opportunity the container is committed to |
| `availability_mode` | string | `kit`, `hybrid`, or `transport` |
| `notes` | string\|null | Free-text notes |

## Show Container

```
GET /api/v1/containers/{container}
```

Returns the container in a `container` key, always including its currently-packed contents under `items` (the active membership rows).

```json
{
    "container": {
        "id": 1,
        "name": "Lighting Kit A",
        "status": "open",
        "availability_mode": "kit",
        "items": [
            {
                "id": 5,
                "container_id": 1,
                "serialised_item_id": 88,
                "product_id": 42,
                "packed_at": "2026-06-19T10:05:00.000Z",
                "packed_by_user_id": 3,
                "unpacked_at": null,
                "unpacked_reason": null,
                "transferred_to_container_id": null,
                "position": "Slot 1",
                "notes": null
            }
        ]
    }
}
```

## Pack Item

```
POST /api/v1/containers/{container}/pack
```

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `serialised_item_id` | int | Yes | Serialised stock level to pack (must exist) |
| `position` | string | No | Slot/position label (max 255) |
| `notes` | string | No | Free-text note |

Returns `201 Created` with the new membership row in a `container_item` key.

The pack is rejected with `422 Unprocessable Entity` when:

- the container is not `open`;
- the item is not serialised;
- the item is held at a different store to the container;
- the item is already packed in an active container;
- **the item is already committed to an opportunity** (packing would collide with the existing booking demand);
- nesting the item would exceed the container's maximum nesting depth.

## Unpack Item

```
POST /api/v1/containers/{container}/unpack
```

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `serialised_item_id` | int | Yes | Serialised stock level to unpack |

Soft-closes the active membership and releases its container demand so the item returns to individual availability. Returns `200 OK` with the closed membership row in a `container_item` key. Unpacking an item not packed in this container (or from a non-open container) returns `422`.
