<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Providers\LaravelWatcherServiceProvider;
use App\Console\Commands\PruneRetentionCommand;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        LaravelWatcherServiceProvider::class,
    ])
    ->withCommands([
        PruneRetentionCommand::class,
    ])
    ->withSchedule(function ($schedule) {
        $schedule->command('eye:prune-retention --days=14 --max-events-per-issue=10')->daily();
    })
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
