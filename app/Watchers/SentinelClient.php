<?php

namespace App\Watchers;

use Exception;
use Throwable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class SentinelClient
{
    protected array $breadcrumbs = [];
    protected array $queue = [];
    protected bool $shutdownRegistered = false;
    protected ?string $dsn = null;
    protected ?string $projectId = null;
    protected string $environment;
    protected bool $enabled = true;

    public function __construct(array $config = [])
    {
        $this->dsn = $config['dsn'] ?? env('SENTRY_DSN');
        $this->projectId = $config['project_id'] ?? env('SENTRY_PROJECT_ID');
        $this->environment = $config['environment'] ?? app()->environment();
        $this->enabled = $config['enabled'] ?? ! app()->environment('testing');
    }

    public function capture(Throwable $throwable, array $context = []): void
    {
        if (! $this->enabled) {
            return;
        }

        $payload = [
            'exception_class' => get_class($throwable),
            'message' => $throwable->getMessage(),
            'level' => $throwable instanceof ErrorException ? $this->level($throwable->getSeverity()) : 'error',
            'code' => $throwable->getCode(),
            'stacktrace' => $this->stacktrace($throwable),
            'breadcrumbs' => $this->breadcrumbs,
            'user_context' => $this->userContext(),
            'request' => $this->requestContext(),
            'environment' => $this->environment,
            'release' => $this->release(),
        ];

        $payload = array_merge($payload, $context);
        $payload['request'] = array_merge($payload['request'] ?? [], $context['request'] ?? []);

        $fingerprint = $this->fingerprint($payload['exception_class'], $payload['message'], $payload['stacktrace']);

        if (! $this->rateLimit($fingerprint)) {
            return;
        }

        $this->queue[] = $payload;
        $this->registerShutdown();
    }

    public function breadcrumb(string $type, array $data = []): void
    {
        $this->breadcrumbs[] = array_merge([
            'type' => $type,
            'timestamp' => microtime(true),
        ], $data);

        if (count($this->breadcrumbs) > 30) {
            array_shift($this->breadcrumbs);
        }
    }

    public function slowQuery(string $sql, array $bindings, float $ms): void
    {
        $this->breadcrumb('sql.slow', [
            'query' => $this->normalizeSql($sql),
            'bindings' => $bindings,
            'ms' => round($ms, 2),
        ]);
    }

    public function nPlusOne(string $normalizedSql, int $count): void
    {
        $this->breadcrumb('sql.n_plus_one', [
            'query' => $normalizedSql,
            'count' => $count,
        ]);
    }

    public function tracePerformance(array $trace): void
    {
        $this->queue[] = array_merge($trace, ['type' => 'performance']);
        $this->registerShutdown();
    }

    protected function stacktrace(Throwable $throwable): array
    {
        $frames = [];
        foreach ($throwable->getTrace() as $frame) {
            $file = $frame['file'] ?? null;
            $line = $frame['line'] ?? null;
            $inApp = $file && Str::startsWith($file, app_path());

            $frames[] = [
                'function' => $frame['function'] ?? '?',
                'class' => $frame['class'] ?? null,
                'file' => $file,
                'line' => $line,
                'in_app' => $inApp,
                'context' => $file && is_readable($file) ? $this->sourceContext($file, $line) : null,
            ];
        }

        return $frames;
    }

    protected function sourceContext(string $file, ?int $line): array
    {
        if (! $line) {
            return [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $start = max(0, $line - 8);
        $end = min(count($lines), $line + 7);

        $context = [];
        for ($i = $start; $i < $end; $i++) {
            $context[] = [
                'line' => $i + 1,
                'code' => $lines[$i] ?? '',
                'highlight' => ($i + 1) === $line,
            ];
        }

        return $context;
    }

    protected function userContext(): array
    {
        if (! app()->bound('auth')) {
            return [];
        }

        $user = auth()->user();
        if (! $user) {
            return [];
        }

        return [
            'id' => $user->getAuthIdentifier(),
            'email' => $user->email ?? null,
            'name' => $user->name ?? null,
        ];
    }

    protected function requestContext(): array
    {
        if (! request()->hasServerParameter('REQUEST_URI')) {
            return [];
        }

        $request = request();

        return [
            'method' => $request->method(),
            'url' => URL::full(),
            'route' => $request->route()?->getName(),
            'controller' => $request->route()?->getAction()['controller'] ?? null,
            'headers' => $this->scrub($request->headers->all()),
            'body' => $this->scrub($request->all()),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'app_env' => app()->environment(),
        ];
    }

    protected function release(): ?string
    {
        static $release = null;
        if ($release !== null) {
            return $release;
        }

        $hash = @exec('git rev-parse --short HEAD 2>/dev/null');
        return $release = $hash ? trim($hash) : null;
    }

    protected function level(int $severity): string
    {
        return match ($severity) {
            E_USER_ERROR, E_ERROR => 'fatal',
            E_USER_WARNING, E_WARNING => 'warning',
            E_USER_NOTICE, E_NOTICE => 'error',
            default => 'error',
        };
    }

    protected function fingerprint(string $class, string $message, array $stacktrace): string
    {
        $normalized = preg_replace(
            '/\b\d{4,}\b|[0-9a-f]{8,}|(\d{4}-\d{2}-\d{2}[\sT]\d{2}:\d{2}:\d{2})/',
            '*',
            $message
        );

        $culprit = null;
        foreach ($stacktrace as $frame) {
            if (! empty($frame['in_app'])) {
                $culprit = ($frame['file'] ?? '').':'.($frame['line'] ?? '?');
                break;
            }
        }

        return hash('sha256', $class.'|'.($culprit ?? 'unknown').'|'.$normalized);
    }

    protected function rateLimit(string $fingerprint): bool
    {
        // At most 10 identical events per minute per fingerprint.
        $key = 'sentinel:rate:'.hash('sha256', $fingerprint);
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, 60);

        return $count <= 10;
    }

    protected function normalizeSql(string $sql): string
    {
        return preg_replace('/\s+/', ' ', trim($sql));
    }

    protected function scrub(array $data): array
    {
        $sensitive = ['password', 'password_confirmation', 'token', 'authorization',
            'secret', 'api_key', 'credit_card'];

        return Arr::except($data, $sensitive);
    }

    protected function registerShutdown(): void
    {
        if ($this->shutdownRegistered) {
            return;
        }

        $this->shutdownRegistered = true;

        register_shutdown_function(function () {
            foreach ($this->queue as $payload) {
                try {
                    if (! empty($payload['dsn'])) {
                        $dsn = $payload['dsn'];
                        unset($payload['dsn']);
                    } else {
                        $dsn = $this->dsn;
                    }

                    if ($dsn) {
                        Http::timeout(1.5)->post($dsn, $payload);
                    }
                } catch (\Throwable $e) {
                    // fail silently: monitoring must never break the app
                }
            }
        });
    }
}
