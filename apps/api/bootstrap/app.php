<?php

use App\Http\Middleware\AuthenticateKiosk;
use App\Http\Middleware\EnsureMember;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Channel auth for the SPA (session cookie) and apps (bearer token) at /api/broadcasting/auth.
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->api(append: [SetLocale::class]);
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'staff' => EnsureStaff::class,
            'kiosk' => AuthenticateKiosk::class,
            'member' => EnsureMember::class,
        ]);
        // Tenant scoping must be active before route-model binding resolves {branch}, {staff}.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: ResolveTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
