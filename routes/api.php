<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\RiderController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // customer bookings
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/bookings/{id}', [BookingController::class, 'show']);

    // rider
    Route::get('/rider/dashboard', [RiderController::class, 'dashboard']);
    Route::post('/rider/status', [RiderController::class, 'setStatus']);
    Route::post('/rider/bookings/{id}/accept', [RiderController::class, 'accept']);
    Route::get('/rider/active-booking', [RiderController::class, 'active']);
    Route::get('/rider/bookings/{id}', [RiderController::class, 'showBooking']);
    Route::post('/rider/bookings/{id}/pickup', [RiderController::class, 'confirmPickup']);
    Route::post('/rider/bookings/{id}/dropoff', [RiderController::class, 'confirmDropoff']);
    Route::get('/rider/earnings', [RiderController::class, 'earnings']);

});     