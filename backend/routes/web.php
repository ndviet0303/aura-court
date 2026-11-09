<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // add-local-api-docs D6: the docs route only exists locally, so only advertise it there.
    return response()->json(array_filter([
        'name' => 'Aura Court API',
        'status' => 'ok',
        'docs' => app()->isLocal() ? '/docs/api' : null,
    ]));
});

Route::get('/reset-password/{token}', function (Request $request, string $token) {
    $frontend = rtrim((string) config('app.frontend_url'), '/');
    $query = http_build_query(['token' => $token, 'email' => $request->query('email')]);

    return redirect()->away($frontend.'/reset-password?'.$query);
})->name('password.reset');
