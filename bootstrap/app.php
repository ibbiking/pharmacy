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
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    // Event auto-discovery is OFF on purpose: under Laravel 8 the App\Listeners\NotifyStockAlert
    // listener was never registered, so it never ran. Auto-discovery would silently enable it, and it
    // notifies *every* user across *all* businesses (cross-tenant). Register listeners explicitly in
    // AppServiceProvider::boot() via Event::listen() if/when one should actually run.
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware): void {
        // Unauthenticated users hitting an `auth` route go to the admin login page;
        // authenticated users hitting a `guest` route (login/signup) go to the dashboard.
        $middleware->redirectGuestsTo(fn (Request $request) => route('login'));
        $middleware->redirectUsersTo('/admin/dashboard');

        // Never trim password fields (carried over from the old App\Http\Middleware\TrimStrings).
        $middleware->trimStrings(except: [
            'current_password',
            'password',
            'password_confirmation',
        ]);

        // Trust X-Forwarded-* headers from proxies/load balancers (old App\Http\Middleware\TrustProxies).
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_AWS_ELB);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'business' => \App\Http\Middleware\EnsureUserHasBusiness::class,
            'restrict.sales-person' => \App\Http\Middleware\RestrictSalesPersonAccess::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
        ]);
    })
    ->create();
