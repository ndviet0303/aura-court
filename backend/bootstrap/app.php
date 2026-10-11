<?php

use App\Http\Middleware\EnsureAccountNotRestricted;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsureOrganizationAdminOrStaff;
use App\Http\Middleware\EnsureTournamentRole;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withCommands([__DIR__.'/../app/Console/Commands'])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'venue_admin' => EnsureOrganizationAdminOrStaff::class,
            'feature' => EnsureFeatureEnabled::class,
            'not_restricted' => EnsureAccountNotRestricted::class,
            'tournament_role' => EnsureTournamentRole::class,
        ]);
        $middleware->append(SetLocale::class);
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();

if ($storagePath = env('LARAVEL_STORAGE_PATH')) {
    $app->useStoragePath($storagePath);
}

return $app;
