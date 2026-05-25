<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.token' => \App\Http\Middleware\AuthenticateWithApiToken::class,
            'tenant.scope' => \App\Http\Middleware\TenantScope::class,
            'active.tenant'=> \App\Http\Middleware\SetActiveTenant::class,
        ]);

        // Use custom Authenticate middleware
        $middleware->replace(
            \Illuminate\Auth\Middleware\Authenticate::class,
            \App\Http\Middleware\Authenticate::class
        );

        // Don't redirect unauthenticated API requests to login
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null; // Return 401 JSON instead of redirecting
            }
            return route('login'); // Regular web requests redirect to login
        });



        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function ($request) {
            return str_starts_with($request->path(), 'api/') || $request->expectsJson();
        });
    })->create();
