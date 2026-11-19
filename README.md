# EagleEye

A **$0/month, self-hosted Sentry clone for Laravel**. EagleEye captures unhandled exceptions, fatal errors, slow SQL queries, N+1 bottlenecks, slow requests, and failed queue jobs from your Laravel apps — groups duplicate errors intelligently by fingerprint — and surfaces them in a dark-mode triage dashboard with stack traces, breadcrumbs, and request context. Alerts fire to free Discord webhooks or Telegram bots.

Runs entirely on free infrastructure: a single Laravel app + SQLite (WAL) on any server you already own, or Cloudflare Workers + D1 for the ingestion API.

```
┌─────────────┐     HTTPS/JSON      ┌──────────────────┐
│  Your App   │ ───────────────────►│  EagleEye API    │
│  (client)   │   POST /api/eye/ingest│  (this repo)    │
└─────────────┘    X-Watcher-Key     └──────────────────┘
                                                      │
                                                      ▼
                                            ┌────────────────┐
                                            │  Dashboard     │
                                            │  (Blade UI)    │
                                            └────────────────┘
```

## Features

- **Exception & fatal capture** — hooks Laravel's exception handler (`reportable`) and `register_shutdown_function` for fatal/timeout errors.
- **Local source context** — extracts ±7 lines of actual PHP around each stack frame straight from the filesystem (no paid symbolication server).
- **Rich request & user context** — authenticated user, method, URL, route, controller, PHP/Laravel versions, git release, sanitized request body/headers.
- **Secret scrubbing** — automatically strips `password`, `token`, `authorization`, `secret`, `api_key`, `credit_card` before anything leaves the app.
- **Rolling breadcrumbs** — last 30 events: SQL queries with bindings + ms, cache hits, queue jobs, outbound HTTP calls, log entries.
- **APM-lite** — request duration, slow queries (>200 ms), N+1 query detection, slow routes, failed/slow queue jobs.
- **Smart fingerprinting** — SHA-256 of `exception_class + top in-app file:line + normalized message` so `Invoice #8821 failed` and `Invoice #8822 failed` merge into **one issue**.
- **Free-tier guardrails** — 256 KB payload cap, max 10 identical events/minute per fingerprint, only latest 10 events kept per issue, raw events/traces pruned after 14 days.
- **Instant alerts** — Discord embed or Telegram message on brand-new issues and resolved-issue regressions (free webhooks, unlimited).
- **Dashboard** — issues list (filter by project/status/environment, sort by last seen or occurrences), issue detail inspector (clickable stack frames with syntax-highlighted source, breadcrumbs timeline, user/request panel, Resolve/Ignore/Delete/Copy cURL), slow-endpoints leaderboard.

## What's in this repo

| Path | Purpose |
|------|---------|
| `app/Watchers/SentinelClient.php` | Drop-in client watcher for monitored Laravel apps |
| `app/Providers/LaravelWatcherServiceProvider.php` | Client-side service provider wiring |
| `app/Http/Controllers/Eye/IngestionController.php` | DSN-auth ingestion, fingerprinting, dedup, alerts |
| `app/Http/Controllers/Eye/IssueController.php` | Issue CRUD + copy-cURL |
| `app/Http/Controllers/Eye/PerformanceController.php` | Slow routes / traces view |
| `app/Console/Commands/PruneRetentionCommand.php` | Daily 14-day prune + per-issue event cap |
| `database/migrations/2026_11_20_000000_create_eye_tables.php` | `projects`, `issues`, `events`, `performance_traces` |
| `app/Models/{Project,Issue,Event,PerformanceTrace}.php` | Eloquent models |
| `resources/views/eye/` | Blade + Tailwind dark-mode dashboard |
| `routes/api.php`, `routes/web.php` | API + dashboard routes |
| `tests/Feature/EyeIngestionTest.php` | Fingerprint dedup, rate limiting, 401 on bad key |
| `docs/DEPLOYMENT.md` | Step-by-step $0/month deployment instructions |

## Quick start — deploy EagleEye

Requirements: PHP 8.1+, Composer, a writable directory for SQLite.

```bash
git clone https://github.com/DMdarryl/EagleEye.git
cd EagleEye
composer install --no-dev -o
cp .env.example .env
php artisan key:generate
php artisan migrate --force
```

Add to the server crontab so the daily retention prune runs:

```bash
* * * * * cd /path/to/EagleEye && php artisan schedule:run >> /dev/null 2>&1
```

Create a project to get a DSN key:

```bash
php artisan tinker
>>> use App\Models\Project;
>>> Project::create([
      'name' => 'My App',
      'dsn_key' => 'choose-a-secret-key',
      'environment' => 'production',
      'discord_webhook_url' => 'https://discord.com/api/webhooks/...',   # optional
      'telegram_bot_token' => '123456:ABC-DEF',                         # optional
      'telegram_chat_id' => '-1001234567890',                          # optional
    ]);
```

The ingestion endpoint is:

```
POST https://your-eagle-eye.domain/api/eye/ingest
X-Watcher-Key: <dsn_key>
Content-Type: application/json
```

Full deploy instructions (including Cloudflare Workers/D1 option): [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md).

## Integrate the client in a monitored app

Copy these two files into the Laravel app you want to monitor:

- `app/Watchers/SentinelClient.php`
- `app/Providers/LaravelWatcherServiceProvider.php`

Register the provider in `config/app.php` (or `bootstrap/app.php`):

```php
App\Providers\LaravelWatcherServiceProvider::class,
```

Configure the DSN (`.env` or `config/sentinel.php`):

```env
SENTRY_DSN=https://your-eagle-eye.domain/api/eye/ingest
SENTRY_PROJECT_ID=choose-a-secret-key
```

The client then captures exceptions automatically, plus slow queries and N+1 warnings via `DB::listen`, and delivers payloads asynchronously in `terminating()` with a 1.5s timeout — it never slows down or breaks your app.

## Dashboard walkthrough

Open `https://your-eagle-eye.domain/eye/issues`:

1. **Issues list** — every unique error is one issue. Filter by project, environment, status (`unresolved`/`resolved`/`ignored`), sort by last seen or occurrences.
2. **Issue detail** — click an issue to see:
   - **Stack trace** — click any frame to expand ±7 lines of syntax-highlighted PHP source; `in-app` frames highlighted in green, `vendor` frames grey.
   - **Breadcrumbs timeline** — the last 30 events leading up to the crash (SQL with ms, cache, logs, outbound HTTP).
   - **User & request context** — user ID/email, route/controller, sanitized body/headers, PHP/Laravel versions, release.
   - **Actions** — Resolve, Ignore, Delete, Copy cURL (replay the exact payload).
3. **Performance** — `/eye/performance` shows the slowest-endpoints leaderboard (avg duration, hits) and recent traces with query counts.

## API reference

`POST /api/eye/ingest`
- Header: `X-Watcher-Key: <dsn_key>` (required)
- Body (JSON):
  ```json
  {
    "exception_class": "App\\Exceptions\\InvoiceFailed",
    "message": "Invoice #8821 failed",
    "level": "error",
    "code": 0,
    "stacktrace": [
      { "function": "charge", "class": "App\\Services\\Invoice", "file": "app/Services/Invoice.php", "line": 42, "in_app": true }
    ],
    "breadcrumbs": [{ "type": "sql", "query": "...", "ms": 12.5 }],
    "user_context": { "id": 1, "email": "user@example.com", "name": "User" },
    "request": { "method": "POST", "url": "...", "route": "invoices.pay", "body": { ... } },
    "environment": "production",
    "release": "a1b2c3d"
  }
  ```
- Responses: `201` created, `401` invalid key, `413` payload > 256 KB, `422` validation error.

## Storage & retention (free-tier safe)

- Raw `events` and `performance_traces` older than **14 days** are deleted daily (`php artisan eye:prune-retention --days=14 --max-events-per-issue=10`, scheduled via Laravel Scheduler).
- Only the **latest 10 events per issue** are kept; older ones are trimmed.
- Grouped `issues` are kept forever.
- Client rate-limits to **10 identical events per minute** per fingerprint to protect write quotas.
- Payloads larger than **256 KB** are rejected.

This keeps a SQLite DB well under the 1–5 GB free-tier limits indefinitely.

## Tests

```bash
vendor/bin/phpunit
```

Covers fingerprint dedup, the 10/minute rate limit, and 401 on invalid keys.

## Security

- DSN keys are bearer tokens — send only over HTTPS.
- The client scrubs known-sensitive fields before transmission; review `SentinelClient.php` to add your app's fields.
- The dashboard is not authenticated by default — put it behind your own auth or a VPN if exposed publicly.

## License

MIT. Built to keep Laravel observability at $0/month.

```
Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
🤖 Generated with [Claude Code](https://claude.com/claude-code)
```
