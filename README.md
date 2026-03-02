# Signals Rental Framework

An open-source rental management framework for equipment rental and event hire companies. Covers the full rental lifecycle — quoting, ordering, invoicing, asset management, availability, scheduling, maintenance, and CRM.

MIT licensed. Self-hostable. Plugin-extensible.

[Documentation](https://docs.signals.rent) | [API Reference](https://docs.signals.rent/docs/api)

## Features

- **Availability Engine** — real-time asset availability across locations, accounting for bookings, maintenance, and transit
- **Quotes & Orders** — full opportunity lifecycle from quote to confirmation, delivery, return, and invoice
- **Invoicing & Payments** — multi-currency invoicing with configurable tax rules and payment tracking
- **CRM** — contacts, organisations, and venues unified under a single members system
- **Stock Management** — inventory tracking, serial numbers, bulk assets, and warehouse locations
- **Crew & Services** — staff scheduling, service management, and resource allocation
- **Maintenance** — PAT testing, safety checks, service schedules, and compliance tracking per asset
- **REST API** — full API for every resource with OpenAPI docs, webhooks, and token auth
- **Plugin System** — install, build, or sell plugins

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.4, Laravel 12 |
| Frontend | Livewire 4, Tailwind CSS 4 |
| Database | PostgreSQL 15+ |
| Queue | Redis + Horizon |
| Websockets | Laravel Reverb |
| Testing | Pest 4 |

## Requirements

- PHP 8.4+
- PostgreSQL 15+
- Node.js 20+
- Composer 2.x
- Redis 7+ (recommended, database fallback available)

## Quick Start

```bash
git clone https://github.com/signals-rental/framework.git
cd framework
composer setup
```

`composer setup` installs dependencies, generates an app key, builds frontend assets, and launches the install wizard.

Three commands get you running:

| Step | Command | What it does |
|:----:|---------|--------------|
| 1 | `composer setup` | Install dependencies, generate app key, build frontend |
| 2 | `php artisan signals:install` | Configure database, Redis, S3, and websockets |
| 3 | `php artisan signals:setup` | Set up company, stores, branding, and admin account |

## Development

Start all services concurrently:

```bash
composer dev
```

This runs the web server, queue worker, log viewer, Vite dev server, and Reverb websocket server.

### Testing

```bash
php artisan test --parallel
```

### Linting

```bash
vendor/bin/pint            # fix formatting
vendor/bin/phpstan analyse # static analysis
```

## Non-Interactive Install

For CI/CD or automated deployments:

```bash
php artisan signals:install --no-interaction \
    --db-host=127.0.0.1 \
    --db-password=secret \
    --cache-driver=redis \
    --storage-driver=local \
    --app-url=https://signals.example.com
```

## License

Signals Rental Framework is open-source software licensed under the [MIT license](LICENSE).
