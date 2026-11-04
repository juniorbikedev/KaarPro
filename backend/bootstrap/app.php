<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\VerifierRole::class,
            'partenaire.valide' => \App\Http\Middleware\PartenaireValide::class,
        ]);
        $middleware->api(append: [\App\Http\Middleware\EnTetesSecurite::class]);

        // En production, l'API est derrière le proxy HTTPS (Caddy) : on lui fait confiance pour savoir que la requête était chiffrée
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_HOST);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // L'API répond toujours en JSON, même pour une route appelée sans en-tête Accept
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*'));
    })->create();
