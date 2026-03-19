# Currencies API

Read-only endpoints for listing and retrieving currency definitions.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/currencies` | List currencies |
| GET | `/api/v1/currencies/{id}` | Show a currency |

## Authentication

Requires a Sanctum bearer token with `currencies:read` ability.

## List Currencies

```
GET /api/v1/currencies
```

### Filters

| Parameter | Description |
|-----------|-------------|
| `q[code_eq]=GBP` | Exact match on ISO 4217 code |
| `q[name_cont]=pound` | Name contains |
| `q[is_enabled_eq]=true` | Filter by enabled status |

### Sort

| Parameter | Description |
|-----------|-------------|
| `sort=code` | Sort by currency code |
| `sort=name` | Sort by name |

### Response

```json
{
  "currencies": [
    {
      "id": 1,
      "code": "GBP",
      "name": "British Pound Sterling",
      "symbol": "£",
      "decimal_places": 2,
      "symbol_position": "before",
      "thousand_separator": ",",
      "decimal_separator": ".",
      "is_enabled": true,
      "created_at": "2026-01-15T00:00:00+00:00",
      "updated_at": "2026-01-15T00:00:00+00:00"
    }
  ],
  "meta": { "total": 30, "per_page": 20, "page": 1 }
}
```

## Show Currency

```
GET /api/v1/currencies/{id}
```

### Response

```json
{
  "currency": {
    "id": 1,
    "code": "GBP",
    "name": "British Pound Sterling",
    "symbol": "£",
    "decimal_places": 2,
    "symbol_position": "before",
    "thousand_separator": ",",
    "decimal_separator": ".",
    "is_enabled": true,
    "created_at": "2026-01-15T00:00:00+00:00",
    "updated_at": "2026-01-15T00:00:00+00:00"
  }
}
```
