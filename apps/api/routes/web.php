<?php

use Illuminate\Support\Facades\Route;

// The API is headless; the web UI lives in apps/web.
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => url('/api/v1'),
]));
