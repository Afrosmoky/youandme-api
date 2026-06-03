<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Stateless API: never redirect unauthenticated requests to a login
        // page (there is none). Without this, the auth middleware eagerly calls
        // route('login') while building the AuthenticationException and 500s
        // before it can be rendered. Returning null lets it surface as a 401
        // (rendered as JSON for api/*, see withExceptions).
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always render JSON for the API. Without this, an unauthenticated
        // non-JSON request to an auth:sanctum route tries to redirect to a
        // (nonexistent) `login` route and 500s instead of returning 401.
        $exceptions->shouldRenderJsonWhen(function ($request, Throwable $e) {
            return $request->expectsJson() || str_starts_with($request->path(), 'api/');
        });
    })->create();
