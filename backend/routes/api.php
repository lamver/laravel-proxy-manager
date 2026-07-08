<?php

use App\Http\Controllers\Api\ProxyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// File import proxy list
Route::post('proxies/import', [ProxyController::class, 'import']);

// CRUD 
Route::apiResource('proxies', ProxyController::class);

// Checker
Route::post('proxies/{proxy}/check', [ProxyController::class, 'check']);
