<?php

namespace App\Providers;

use App\Watchers\SentinelClient;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Throwable;

class LaravelWatcherServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SentinelClient::class, function ($app) {
            return new SentinelClient(config('sentinel', []));
        });
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole() && ! app()->environment('testing')) {
            $this->watchExceptions();
            $this->watchQueries();
            $this->watchPerformance();
        }

        if ($this->app->runningInConsole()) {
            $this->watchQueueFailures();
        }
    }

    protected function watchExceptions(): void
    {
        $client = app(SentinelClient::class);

        // Unhandled exceptions reported through Laravel's exception handler.
        $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->reportable(function (Throwable $throwable) use ($client) {
                $client->capture($throwable);
            });
    }

    protected function watchQueries(): void
    {
        $client = app(SentinelClient::class);

        DB::listen(function ($event) use ($client) {
            $client->breadcrumb('sql', [
                'query' => $event->sql,
                'bindings' => $event->bindings,
                'ms' => round($event->time, 2),
            ]);

            if ($event->time > 200) {
                $client->slowQuery($event->sql, $event->bindings, $event->time);
            }
        });
    }

    protected function watchPerformance(): void
    {
        $client = app(SentinelClient::class);

        $this->app->terminating(function () use ($client) {
            $duration = (int) ((microtime(true) - LARAVEL_START) * 1000);
            $queryCount = count(DB::getQueryLog());

            $client->tracePerformance([
                'duration_ms' => $duration,
                'query_count' => $queryCount,
            ]);
        });
    }

    protected function watchQueueFailures(): void
    {
        $client = app(SentinelClient::class);

        Queue::failing(function ($event) use ($client) {
            $client->capture(new \Exception($event->exception->getMessage()), [
                'level' => 'error',
                'exception_class' => get_class($event->exception),
                'request' => ['job' => get_class($event->job), 'queue' => $event->job->getQueue() ?? 'default'],
            ]);
        });
    }
}
