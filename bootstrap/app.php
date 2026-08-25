<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Configuration\Routing;

return Application::configure(basePath: dirname(__DIR__))
    ->withEvents(discover: false)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies (ngrok, Cloudflare, load balancers, etc.)
        // so that X-Forwarded-* headers are respected.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'commuter_session' => \App\Http\Middleware\EnsureCommuterSession::class,
        ]);
        $middleware->append(\App\Http\Middleware\SecurityHeadersMiddleware::class);
        $middleware->append(\App\Http\Middleware\DisableLoopbackViteHotForExternalRequests::class);
        $middleware->append(\App\Http\Middleware\AuthLifecycleDiagnostics::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
