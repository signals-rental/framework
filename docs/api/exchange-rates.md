# Exchange Rates API

Full CRUD endpoints for managing currency exchange rates. Requires `settings.manage` permission.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/exchange_rates` | List exchange rates |
| GET | `/api/v1/exchange_rates/{id}` | Show an exchange rate |
| POST | `/api/v1/exchange_rates` | Create an exchange rate |
| PUT | `/api/v1/exchange_rates/{id}` | Update an exchange rate |
| DELETE | `/api/v1/exchange_rates/{id}` | Delete an exchange rate |

## Authentication

Requires a Sanctum bearer token with `exchange_rates:read` (GET) or `exchange_rates:write` (POST/PUT/DELETE) ability.

## List Exchange Rates

```
GET /api/v1/exchange_rates
```

### Filters

| Parameter | Description |
|-----------|-------------|
| `q[source_currency_code_eq]=GBP` | Filter by source currency |
| `q[target_currency_code_eq]=USD` | Filter by target currency |
| `q[source_eq]=manual` | Filter by rate source |

### Sort

`sort=effective_at`, `sort=source_currency_code`, `sort=target_currency_code`

## Create Exchange Rate

```
POST /api/v1/exchange_rates
```

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `source_currency_code` | string | Yes | ISO 4217 code (must exist in currencies table) |
| `target_currency_code` | string | Yes | ISO 4217 code (must differ from source) |
| `rate` | string | Yes | Exchange rate (positive number) |
| `inverse_rate` | string | No | Auto-computed as `1/rate` if not provided |
| `source` | string | No | Rate source (default: "manual") |
| `effective_at` | datetime | No | When rate becomes effective (default: now) |
| `expires_at` | datetime | No | When rate expires (must be after effective_at) |

### Response (201)

```json
{
  "exchange_rate": {
    "id": 1,
    "source_currency_code": "GBP",
    "target_currency_code": "USD",
    "rate": "1.27000000",
    "inverse_rate": "0.78740157",
    "source": "manual",
    "effective_at": "2026-01-15T00:00:00+00:00",
    "expires_at": null,
    "created_at": "2026-01-15T00:00:00+00:00",
    "updated_at": "2026-01-15T00:00:00+00:00"
  }
}
```

## Rate Triangulation

When converting between two currencies with no direct or inverse exchange rate, the system automatically triangulates through the base currency (configured in Settings > Company > Base Currency). For example, with base currency USD:

- GBP → JPY = (GBP → USD rate) × (USD → JPY rate)
