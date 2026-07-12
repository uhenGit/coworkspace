<?php

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\SpaceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('spaces', SpaceController::class);

    Route::apiResource('bookings', BookingController::class);

    Route::apiResource('categories', CategoryController::class);

    Route::get('/my-bookings', [BookingController::class, 'myBookings']);
});
