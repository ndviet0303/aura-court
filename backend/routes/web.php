<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/reset-password/{token}', function (Request $request, string $token) {
    $frontend = rtrim((string) config('app.frontend_url'), '/');
    $query = http_build_query(['token' => $token, 'email' => $request->query('email')]);

    return redirect()->away($frontend.'/reset-password?'.$query);
})->name('password.reset');
