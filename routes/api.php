<?php

use App\Http\Controllers\Api\ChannelController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| Channel Monitoring API
|--------------------------------------------------------------------------
*/

// For now, we'll make these available without authentication for development
Route::prefix('channels')->group(function () {
    Route::get('/', [ChannelController::class, 'index']);
    Route::post('/', [ChannelController::class, 'store']);
    Route::get('/live', [ChannelController::class, 'live']);
    Route::get('/offline', [ChannelController::class, 'offline']);
    Route::get('/{id}', [ChannelController::class, 'show']);
    Route::put('/{id}', [ChannelController::class, 'update']);
    Route::delete('/{id}', [ChannelController::class, 'destroy']);
    Route::post('/{id}/check', [ChannelController::class, 'check']);
});
