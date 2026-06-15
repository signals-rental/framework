---
title: Multi-Currency & Tax
description: Currencies, exchange rates, and the tax resolution engine — the financial foundation Signals ships in Phase 2.
---

## Overview

Signals ships a complete financial **engine** for multi-currency and tax: a catalogue of ISO 4217 currencies, dated exchange rates with lossless conversion, and a rule-based tax resolution engine. These are the building blocks the rest of the platform draws on when money is involved.

> **Important — Phase 2 ships the engine, not the application.** In Phase 2 the currency and tax engines exist and are fully usable on their own (and through the API), but they are **not yet applied automatically to order or invoice line items**. Per-line currency context and tax resolution on opportunities and invoices is intentionally deferred to **Phase 3 (Opportunities)** and **Phase 4 (Invoicing)**. See [Deferred to Phase 3 / 4](#deferred-to-phase-3-4) below.

## Currencies

Signals maintains a table of world currencies based on ISO 4217. Each currency records the data needed to store and format monetary values correctly.

| Field | Description |
|-------|-------------|
| `code` | ISO 4217 three-letter code (e.g. `GBP`, `USD`, `EUR`) |
| `name` | Full currency name |
| `symbol` | Display symbol (e.g. `£`, `$`, `€`) |
| `decimal_places` | Number of minor-unit digits (2 for most currencies, 0 for JPY) |
| `symbol_position` | Whether the symbol renders `before` or `after` the amount |
| `thousand_separator` / `decimal_separator` | Grouping and decimal punctuation for display |
| `is_enabled` | Whether the currency is available for selection |

Currencies are reference data — they are read-only over the API. The [Currencies API](/docs/api/currencies) lists and retrieves them.

### Base Currency

The **base currency** is configured under **Settings → Company → Base Currency** (`company.base_currency`, defaulting to `GBP`). It is the currency all conversions triangulate through when no direct exchange rate exists between two currencies.

## Exchange Rates

Exchange rates are **dated** — each rate has an `effective_at` (and optional `expires_at`), so historical conversions stay accurate and you can schedule future rates. Rates are managed through the admin UI and the [Exchange Rates API](/docs/api/exchange-rates).

| Field | Description |
|-------|-------------|
| `source_currency_code` / `target_currency_code` | The currency pair (must differ) |
| `rate` | The conversion rate from source to target |
| `inverse_rate` | Auto-computed as `1 / rate` when not supplied |
| `source` | Where the rate came from (e.g. `manual`) |
| `effective_at` / `expires_at` | The window during which the rate applies |

### Conversion and Triangulation

Conversions use `brick/money` with `RationalMoney` for **lossless intermediate arithmetic**, rounding only at the final step to the target currency's minor unit. When converting between two currencies with no direct or inverse rate on file, the engine **triangulates** through the base currency:

```
GBP → JPY  =  (GBP → base) × (base → JPY)
```

Triangulation is non-recursive — it resolves each leg directly and multiplies the two rates.

## Tax Engine

Tax in Signals is **resolved, not hard-coded**. There is no single "tax rate" field on a product. Instead, the engine resolves the correct treatment for a given net amount in context, by matching the relevant tax classes against a configurable rule matrix.

The moving parts (all managed under [Tax Classes](/docs/platform/tax-classes)):

| Concept | Role |
|---------|------|
| **Product tax classes** | Categorise items by tax treatment (Standard, Reduced, Zero, Exempt) |
| **Organisation tax classes** | Categorise members by tax status (Standard, Exempt, Reverse Charge) |
| **Tax rates** | Named percentages (e.g. "UK Standard" at 20%) |
| **Tax rules** | Map an organisation class + product class → a tax rate, with a priority |

### Resolution

Given an organisation tax class and a product tax class, the engine selects the **highest-priority active tax rule** for that pair. If no exact rule matches, it falls back to the rule for the **default** organisation and product tax classes. If no rule (or no active rate) applies, the result is zero tax. Tax is calculated on the net amount in **minor units** using `bcmath`, then rounded to the currency's minor unit per line.

## Deferred to Phase 3 / 4

Phase 2 delivers the engines above as standalone, tested services (`CurrencyService`, `TaxCalculator`) and their supporting reference data, settings, and API endpoints. What Phase 2 deliberately does **not** include is the wiring of these engines into transactional line items:

- **Per-line currency context** — `currency_code`, `exchange_rate`, and `exchange_rate_locked` on opportunities, and the recalculation of line totals when the document currency changes, are part of **Phase 3 (Opportunities)**.
- **Per-line tax application** — resolving and storing tax treatment, rate, and amount on each opportunity item (and re-resolving when a line changes) is part of **Phase 3 (Opportunities)**.
- **Invoice tax snapshots** — invoice-line tax columns, the aggregated `tax_summary`, credit-note tax handling, and purchase-order tax are part of **Phase 4 (Invoicing)**.

This separation is intentional: the engine is correct and reusable now, and the application layer that calls it lands with the order and invoice domains it belongs to. Until then, multi-currency and tax can be exercised directly through the services and the API, but they are not automatically applied to orders or invoices.
