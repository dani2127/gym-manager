<?php

use App\Http\Controllers\Api\V1\MetaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', [MetaController::class, 'health']);
    Route::get('/meta/config', [MetaController::class, 'config']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', fn (Request $request) => $request->user());
    });
});
