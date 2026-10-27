# Gym Platform

Multi-branch, SaaS-ready gym management: prepaid memberships in ETB, QR
check-in with live front-desk pop-ups, class booking, trainer portals, and
Telegram / WhatsApp notifications. English and Amharic.

**Read [`ARCHITECTURE.md`](ARCHITECTURE.md) first.** It is the master plan:
stack, database schema, flows and the milestone roadmap.

| Path | What |
|---|---|
| `apps/api` | Laravel 13 REST API (`/api/v1`), Sanctum, Reverb |
| `apps/web` | Next.js 16 app: `/` public, `/app` member PWA, `/desk`, `/admin`, `/trainer` |
| `docker-compose.yml` | Single-VPS stack: Caddy, API, queue, scheduler, Reverb, web, MySQL 8, Redis |
| `legacy/` | Original GYM One PHP app, kept read-only for porting |

## Local development

Requirements: PHP 8.3+, Composer, Node 22+.

```bash
# API → http://localhost:8000
cd apps/api
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
php artisan serve

# Web → http://localhost:3000  (second terminal)
cd apps/web
npm install
cp .env.example .env.local
npm run dev
```

Checks: `php artisan test` and `vendor/bin/pint --test` in `apps/api`;
`npm run lint` and `npm run build` in `apps/web`.

Useful endpoints: `GET /api/v1/health`, `GET /api/v1/meta/config`
(send `X-Locale: am` for Amharic).

## Production (single VPS)

1. Point DNS for `{domain}`, `api.{domain}` and `ws.{domain}` at the server.
2. `cp .env.example .env` and replace every `CHANGE_ME`. Set `APP_DOMAIN`,
   the URLs, and an `APP_KEY` (`docker compose run --rm api php artisan key:generate --show`).
3. `docker compose up -d --build`

Caddy obtains TLS certificates automatically. The API container runs
migrations on start; set `RUN_MIGRATIONS=false` in `.env` to manage them
yourself.

## Attribution

The code in `legacy/` is based on GYM One, developed by the GYM One
contributors, and is covered by `legacy/LICENSE`.
