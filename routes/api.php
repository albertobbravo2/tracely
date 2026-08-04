<?php

use App\Http\Controllers\Api\ShipmentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('shipments', ShipmentController::class)
    ->parameters(['shipments' => 'shipment:tracking_number']);
