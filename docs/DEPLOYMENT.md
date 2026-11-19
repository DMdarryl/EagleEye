# EagleEye — $0/month Sentry Clone for Laravel

EagleEye is a self-hosted error & performance monitor for Laravel apps. It runs on a single Laravel instance with SQLite (zero external accounts, $0/month), captures exceptions from monitored apps via a drop-in PHP client, groups duplicates by fingerprint, and alerts via free Discord/Telegram webhooks.

## What's included

| Part | File(s) | Purpose |
|------|---------|---------|
| Schema | `database/migrations/2026_11_20_000000_create_eye_tables.php` | `projects`, `issues`, `events`, `performance_traces` |
| Models | `app/Models/{Project,Issue,Event,PerformanceTrace}.php` | Eloquent models |
| Ingestion API | `app/Http/Controllers/Eye/IngestionController.php`, `routes/api.php` | DSN auth, SHA-256 fingerprinting, event cap (10/issue), alerts |
| Dashboard | `app/Http/Controllers/Eye/{Issue,Performance}Controller.php`, `resources/views/eye/` | Issues list, issue detail (stack trace, breadcrumbs, context), performance view |
| Client watcher | `app/Watchers/SentinelClient.php`, `app/Providers/LaravelWatcherServiceProvider.php` | Drop into any monitored Laravel app |
| Retention job | `app/Console/Commands/PruneRetentionCommand.php` | Daily prune (>14 days) + trim excess events per issue |
| Tests | `tests/Feature/EyeIngestionTest.php` | Fingerprint dedup, rate limiting (10/min), 401 on bad key |

## 1. Deploy the EagleEye server

Requirements: PHP 8.1+, Composer, a SQLite-writable directory.

```bash
git clone <this-repo> eagle-eye
cd eagle-eye
composer install --no-dev -o
cp .env.example .env
php artisan key:generate
```

Set these in `.env` (the defaults already work):

```
DB_CONNECTION=sqlite
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-eagle-eye.domain
```

Run migrations and start the scheduler:

```bash
php artisan migrate --force
```

Add to the server's crontab (runs the prune job once daily, plus Laravel's scheduler):

```bash
* * * * * cd /path/to/eagle-eye && php artisan schedule:run >> /dev/null 2>&1
```

The `schedule:run` entry is required; the prune command is registered in `console.php`/kernel and scheduled in `App\Console\Kernel` (or run manually with `php artisan eye:prune-retention`).

Configure scheduled pruning in `routes/console.php` (or the kernel) so it runs daily:

```php
Schedule::command('eye:prune-retention --days=14 --max-events-per-issue=10')->daily();
```

Then create a project via `php artisan tinker` (or the DB directly) to get a DSN key:

```php
use App\Models\Project;
Project::create([
    'name' => 'My App',
    'dsn_key' => 'choose-a-secret-key',
    'environment' => 'production',
    'discord_webhook_url' => 'https://discord.com/api/webhooks/...',
    'telegram_bot_token' => '123456:ABC-DEF',
    'telegram_chat_id' => '-1001234567890',
]);
```

The ingestion endpoint is `POST /api/eye/ingest` with header `X-Watcher-Key: <dsn_key>`.

## 2. Integrate the client in a monitored Laravel app

Copy these two files into the monitored app:

- `app/Watchers/SentinelClient.php`
- `app/Providers/LaravelWatcherServiceProvider.php`

Register the provider in `config/app.php` (or `bootstrap/app.php` providers array):

```php
App\Providers\LaravelWatcherServiceProvider::class,
```

Publish a `config/sentinel.php` (or set `.env`):

```
SENTRY_DSN=https://your-eagle-eye.domain/api/eye/ingest
SENTRY_PROJECT_ID=<dsn_key>
```

The client captures:
- Unhandled exceptions + fatal errors (via `reportable` + `register_shutdown_function`)
- ±7 lines of local PHP source around each stack frame
- Sanitized request/user context (passwords, tokens, secrets scrubbed)
- Rolling breadcrumbs (SQL queries, cache, queue, logs) — last 30
- Slow queries (>200 ms) and N+1 detection
- Async delivery in `terminating()` with a 1.5s timeout; never blocks the app
- Cache-based rate limit: max 10 identical events per minute per fingerprint

## 3. Zero-cost hosting options

### Option B — Self-hosted (recommended, zero accounts)
Run on any server you already own (Oracle Cloud Always-Free ARM instance, a home server, a Raspberry Pi) with PHP + SQLite. Nothing else to pay for.

### Option A — Cloudflare Workers + D1
The ingestion API (`IngestionController`) can be ported to a Cloudflare Worker + D1. The dashboard (Blade) is server-rendered PHP, so it needs a PHP-capable host; pair a Worker (ingestion) with a $0 PHP host for the dashboard, or self-host the whole Laravel app as above. Discord/Telegram alerts work from either.

## 4. Alerts

Set `discord_webhook_url` on a project for Discord embeds, or `telegram_bot_token` + `telegram_chat_id` for Telegram messages. Alerts fire on brand-new issues and on resolved-issue regressions. Both are 100% free, unlimited.

## 5. Retention & storage guardrails

- Raw `events` and `performance_traces` older than 14 days are deleted daily.
- Only the latest 10 events per issue are kept; older ones are trimmed.
- Grouped `issues` are kept forever.
- Payloads larger than 256 KB are rejected.
- Client rate-limits to 10 identical events/minute to protect free-tier write quotas.

These keep a SQLite DB well under the 1–5 GB free-tier limits indefinitely.

## 6. Run the tests

```bash
vendor/bin/phpunit
```

## Security notes

- DSN keys are bearer tokens — treat them like passwords and send over HTTPS only.
- The client scrubs known-sensitive request fields before transmission; review `SentinelClient::$sensitive` for your app's fields.
- The dashboard is not authenticated by default; put it behind your own auth or a VPN if exposed publicly.
