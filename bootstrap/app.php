<?php

use App\Http\Middleware\EnsureCompanyIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Permite que /api/* lea la cookie de sesión cuando la petición sale del
        // propio frontend (dominios en sanctum.stateful). Sin esto el guard
        // `sanctum` no tiene sesión que consultar y siempre devuelve null.
        $middleware->statefulApi();

        $middleware->alias([
            // Empresa desactivada = su gente fuera del backoffice. Se aplica
            // al bloque `backoffice.` de web y al grupo autenticado de la API.
            'company.active' => EnsureCompanyIsActive::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
