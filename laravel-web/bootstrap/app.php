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
    ->withMiddleware(function (Middleware $middleware) {
        // Sanctum for API authentication
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // === STRONGEST DEFENSE against "Route [login] not defined" ===
        // 1. Global guest redirect (Laravel 11+)
        $middleware->redirectTo(guests: '/panel/login');

        // 2. COMPLETELY REPLACE Laravel's default Authenticate middleware class
        //    This is the #1 reason the error happens (it internally calls route('login'))
        $middleware->replace(
            \Illuminate\Auth\Middleware\Authenticate::class,
            \App\Http\Middleware\Authenticate::class
        );

        // 3. Aliases (our custom class is also aliased)
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'panel' => \App\Http\Middleware\PanelMiddleware::class,
            'maintenance' => \App\Http\Middleware\PlatformMaintenanceMiddleware::class,
            'staff.permission' => \App\Http\Middleware\CheckStaffPermission::class,
            'auth' => \App\Http\Middleware\Authenticate::class,
        ]);

        // CSRF exclusions
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'admin/*',
            'panel/*',
            'admin/login',
            'panel/login',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // === ULTIMATE FIX: Catch every AuthenticationException and use ONLY direct paths ===
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            $path = $request->path();

            // Always save where they were trying to go
            if (str_starts_with($path, 'panel')) {
                session()->put('url.intended', $request->fullUrl());
                // Direct path — this is the only thing that should ever be returned
                return redirect('/panel/login');
            }

            return redirect('/login');
        });

        // Also handle the base case
        $exceptions->shouldRenderJsonWhen(function ($request, \Throwable $e) {
            return $request->expectsJson();
        });
    })->create();
