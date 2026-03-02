---
title: Introduction
description: Signals Rental Framework is an open-source rental management framework. Free, self-hostable, and plugin-extensible.
---

## What is Signals Rental Framework?

Signals Rental Framework is a production-ready rental management framework built for the equipment rental and event hire industry. It covers the full rental lifecycle — quoting, ordering, invoicing, asset management, availability, scheduling, maintenance, and customer relationships.

It's MIT licensed, self-hostable, and plugin-extensible. The same complete code for every rental business, from a sole trader with a van to a national operation with ten depots.

## Why Signals Rental Framework?

Most rental businesses run on software designed for something else — inventory tools, e-commerce platforms, or ERPs with bolt-on modules. Availability is an afterthought. Scheduling is a spreadsheet. Every customisation is a support ticket.

Signals Rental Framework is different:

- **Purpose-built for rental** — availability, scheduling, maintenance cycles, kit prep, dry hire, wet hire, sub-hire, and cross-hire are the foundation, not afterthoughts
- **Free forever** — MIT licensed with no open-core catch, no features held back for a paid edition, and no time-limited trials
- **No vendor lock-in** — your data, your servers, your rules. You are never dependent on a single company
- **Fully transparent** — every line of code is visible. Audit security, review logic, understand what runs your business

## Key Features

| Feature | Description |
|---------|-------------|
| Availability Engine | Real-time asset availability across all locations, accounting for bookings, maintenance, and transit |
| Quotes and Orders | Full opportunity lifecycle from quote to confirmation, delivery, return, and invoice |
| Invoicing and Payments | Multi-currency invoicing with configurable tax rules and payment tracking |
| CRM | Contacts, organisations, and venues unified under a single members system |
| Stock Management | Inventory tracking, serial numbers, bulk assets, and warehouse locations |
| Crew and Services | Staff scheduling, service management, and resource allocation |
| Maintenance | PAT testing, safety checks, service schedules, and compliance tracking per asset |
| Kit Prep and Dispatch | Pick lists, prep workflows, and dispatch management |
| Real-time Dashboard | KPIs, order pipeline, asset utilisation, alerts, and activity feed |
| REST API | Full API for every resource with OpenAPI documentation, webhooks, and token authentication |
| Plugin System | Install, build, or sell plugins. Community, commercial, and private extensions |

## Architecture

Signals Rental Framework follows a layered, extensible architecture:

| Layer | Purpose |
|-------|---------|
| Your Application | Custom views, routes, and business logic |
| Plugin Layer | Community, commercial, and private plugins |
| Signals Core | Rental engine, API, event system, and data models |
| Laravel + PHP + PostgreSQL | Framework, runtime, and database |

The framework is **API-first** — every capability is accessible programmatically. The web UI and API share business logic through action classes and DTOs, so nothing is buried behind a UI with no way in.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.4, Laravel 12 |
| Frontend | Livewire 4, Tailwind CSS 4 |
| Database | PostgreSQL |
| Queue | Redis + Horizon |
| Websockets | Laravel Reverb |
| Testing | Pest 4 |

## Self-Hosting

Self-hosting is a first-class experience. Deploy to any environment:

- **Bare metal or VPS** — any Linux server with PHP 8.4+, PostgreSQL, and Nginx or Caddy
- **Docker** — official images with docker-compose for dev and production
- **Cloud providers** — AWS, Azure, Google Cloud, or DigitalOcean with Laravel Forge or Ploi support

## Who Is It For?

Signals Rental Frameworkserves every size of rental business with the same code and the same features:

| Segment | Profile |
|---------|---------|
| Sole Trader | 50–500 assets, 1–3 staff. Run on a low-cost VPS with full features. |
| Mid-Market | 500–5,000 assets, 5–50 staff. Multi-depot support, team permissions, custom plugins. |
| Enterprise | 5,000+ assets, 50+ staff. High-availability deployment with custom integrations. |

## Getting Started

```bash
git clone https://github.com/signals-rental/framework.git
cd framework
composer setup
```

This installs dependencies, configures your infrastructure, and launches the setup wizard. See the [Installation](/docs/getting-started/installation) guide for full details.

> **Note:** Signals Rental Frameworkis under active development. The roadmap is public, every decision is discussable, and breaking changes follow semantic versioning.
