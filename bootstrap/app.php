<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsOfficerOrVolunteer;
use App\Http\Middleware\EnsureUserIsAssignedToDisaster;
use App\Http\Middleware\EnsureUserCanAccessAPI;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register role-based middleware aliases
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'admin' => EnsureUserIsAdmin::class,
            'officer_or_volunteer' => EnsureUserIsOfficerOrVolunteer::class,
            'disaster_assigned' => EnsureUserIsAssignedToDisaster::class,
            'api_access' => EnsureUserCanAccessAPI::class,
        ]);

        // Trust proxies: make HTTPS/client IP/host detection reliable behind LB/CDN
        $proxies = env('TRUSTED_PROXIES', '*'); // Prefer listing CIDRs/IPs in production
        $middleware->trustProxies(
            at: $proxies,
            headers: Request::HEADER_X_FORWARDED_FOR
                   | Request::HEADER_X_FORWARDED_HOST
                   | Request::HEADER_X_FORWARDED_PORT
                   | Request::HEADER_X_FORWARDED_PROTO
                   | Request::HEADER_X_FORWARDED_AWS_ELB
        );

        // Optional: restrict hosts to prevent host header issues
        $appHost = env('APP_HOST');
        if ($appHost) {
            $middleware->trustHosts([
                $appHost,
            ]);
        }
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
