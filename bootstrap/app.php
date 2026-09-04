<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Railway (like most PaaS) terminates TLS at its edge proxy and
        // forwards plain HTTP to the container. Without trusting that proxy
        // Laravel builds every URL from the scheme it can see - http - so
        // the stylesheet is blocked as mixed content and the PWA manifest's
        // start_url is unusable. Trusting '*' is safe here precisely because
        // the container is only reachable through that proxy.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
