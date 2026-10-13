<?php

namespace Tests\Unit;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class SetLocaleTest extends TestCase
{
    public function test_sets_vietnamese_by_default(): void
    {
        $middleware = new SetLocale;
        $request = Request::create('/api/courts', 'GET');
        $request->headers->remove('Accept-Language');

        $middleware->handle($request, function ($req) {
            $this->assertEquals('vi', App::getLocale());

            return response()->noContent();
        });
    }

    public function test_sets_vietnamese_from_accept_language(): void
    {
        $middleware = new SetLocale;
        $request = Request::create('/api/courts', 'GET');
        $request->headers->set('Accept-Language', 'vi-VN,vi;q=0.9');

        $middleware->handle($request, function ($req) {
            $this->assertEquals('vi', App::getLocale());

            return response()->noContent();
        });
    }

    public function test_sets_english_from_accept_language_header(): void
    {
        $middleware = new SetLocale;
        $request = Request::create('/api/courts', 'GET');
        $request->headers->set('Accept-Language', 'en-US,en;q=0.9');

        $middleware->handle($request, function ($req) {
            $this->assertEquals('en', App::getLocale());

            return response()->noContent();
        });
    }

    public function test_sets_locale_from_x_locale_header(): void
    {
        $middleware = new SetLocale;
        $request = Request::create('/api/courts', 'GET');
        $request->headers->set('X-Locale', 'en');

        $middleware->handle($request, function ($req) {
            $this->assertEquals('en', App::getLocale());

            return response()->noContent();
        });
    }
}
