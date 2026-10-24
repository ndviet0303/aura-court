<?php

use App\Http\Middleware\EnsureAccountNotRestricted;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsureOrganizationAdminOrStaff;
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
        health: '/up',
    )
    // BR-23: broadcasting/auth gets the same locked-account and revoked-session checks as the API.
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['web', 'not_restricted']])
    ->withCommands([__DIR__.'/../app/Console/Commands'])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'venue_admin' => EnsureOrganizationAdminOrStaff::class,
            'feature' => EnsureFeatureEnabled::class,
            'not_restricted' => EnsureAccountNotRestricted::class,
        ]);
        $middleware->append(SetLocale::class);
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        // A PostgreSQL deadlock (40P01) while locking rolled the whole transaction back: a conflict to retry, not a 500.
        // Nested transactions wrap it in DeadlockException, so the chain is walked; other SQL errors keep the default.
        $exceptions->render(function (PDOException $exception) {
            for ($e = $exception; $e !== null; $e = $e->getPrevious()) {
                if ($e instanceof PDOException && in_array('40P01', [(string) $e->getCode(), $e->errorInfo[0] ?? null], true)) {
                    return response()->json(['message' => 'Hệ thống đang bận xử lý một yêu cầu khác cùng lúc. Vui lòng thử lại.'], 409);
                }
            }

            return null;
        });
    })->create();

if ($storagePath = env('LARAVEL_STORAGE_PATH')) {
    $app->useStoragePath($storagePath);
}

return $app;
