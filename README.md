# Subscription-Platform-Demo

Uptime monitoring SaaS demo built with Laravel 13 + React 19. Stripe recurring subscriptions (Free / Pro), scheduled checks and queued jobs. Practicing full-stack architecture with real-time updates.

> ⚠️ **Disclaimer:** This is a learning project with no real money involved. It runs exclusively against Stripe's **test environment** (test mode / Sandbox) using test API keys (`sk_test_...`). No real payments are processed and no real money is ever moved. **Never enter real card details.** Use the Stripe test cards listed below.

## Features

- Authentication (Laravel Sanctum)
- Uptime monitors: HTTP checks with configurable interval, timeout and expected status
- Scheduled dispatching of checks (Laravel Scheduler) and queued processing (Laravel Queues)
- Per-user subscription plans with recurring billing: **Free** and **Pro**
- Stripe Checkout + Customer Portal via Laravel Cashier
- Plan-based limits (monitor count, check interval, history retention)

## Tech Stack

**Backend**

- Laravel 13
- PostgreSQL - primary database
- Laravel Sanctum - SPA authentication (cookie-based)
- Laravel Reverb - WebSocket, real-time updates
- Laravel Cashier - Stripe
- Laravel Telescope - debugging (local/staging)

**Frontend**

- React 19
- TypeScript
- Vite
- React Router
- Tailwind CSS
- shadcn/ui
- Laravel Echo

**Infrastructure**

- Docker - Containerized development environment
- Docker Compose - Multi-container orchestration

## Architecture

The app runs entirely in Docker: backend, frontend, database, workers and WebSocket server each run in their own container:

| Service                     | Container          | Purpose                                  | Port        |
| --------------------------- | ------------------ | ---------------------------------------- | ----------- |
| nginx                       | `uptime_nginx`     | Web server                               | 8876 → 80   |
| app                         | `uptime_app`       | PHP-FPM (Laravel)                        | –           |
| queue                       | `uptime_queue`     | Queue worker (`queue:listen`)            | –           |
| scheduler                   | `uptime_scheduler` | Task scheduler (`schedule:work`)         | –           |
| reverb                      | `uptime_reverb`    | WebSocket server (Laravel Reverb)        | 8080 → 8080 |
| db                          | `uptime_db`        | PostgreSQL 17                            | 8101 → 5432 |
| pgadmin                     | `uptime_pgadmin`   | Database GUI                             | 8102 → 80   |
| node                        | `uptime_node`      | Vite dev server (React frontend)         | 5173 → 5173 |
| mailpit                     | `uptime_mailpit`   | Catches outgoing emails                  | 8025 → 8025 |
| stripe _(profile `stripe`)_ | `uptime_stripe`    | Stripe CLI, forwards webhooks to `nginx` | –           |

The React SPA communicates with the Laravel API over HTTP and subscribes to Reverb over WebSocket for real-time updates.

## Project Structure

```
.
├── _docker/              # PHP Dockerfile, nginx config
├── backend/              # Laravel 13 API
├── frontend/             # React 19 + TypeScript SPA
├── docker-compose.yml
├── .env.example          # variables for docker-compose
└── README.md
```

### Test cards

| Scenario                                    | Card number           |
| ------------------------------------------- | --------------------- |
| Successful payment                          | `4242 4242 4242 4242` |
| Payment requires authentication (3D Secure) | `4000 0025 0000 3155` |
| Card declined                               | `4000 0000 0000 9995` |

Use any future expiry date, any 3-digit CVC and any postal code.

## Pricing Tiers (demo)

|                    | Free   | Pro     |
| ------------------ | ------ | ------- |
| Monitors           | 3      | 50      |
| Min check interval | 15 min | 5 min   |
| History retention  | 3 days | 90 days |
| Alert channels     | Email  | Email   |

## Roadmap

- [x] Docker setup
- [ ] Laravel + React scaffolding
- [ ] Auth
- [ ] Monitors CRUD + scheduled checks
- [ ] Incidents + notifications
- [ ] Real-time updates (Reverb)
- [ ] Stripe subscriptions (Cashier) + plan limits
- [ ] Redis + Horizon
- [ ] Public status pages

## Disclaimer

This is a study project. It is not intended for production use and is not affiliated with Stripe. Feel free to fork and experiment with the codebase.
