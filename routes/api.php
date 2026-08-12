<?php

use App\Http\Controllers\Api\ShipmentController;
use Illuminate\Support\Facades\Route;

Route::get('shipments/{shipment:tracking_number}', [ShipmentController::class, 'show'])
    ->middleware('throttle:show-shipment')
    ->name('shipments.show');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('shipments', [ShipmentController::class, 'index'])
        ->middleware('permission:ver pedido,sanctum')
        ->name('shipments.index');

    Route::post('shipments', [ShipmentController::class, 'store'])
        ->middleware('permission:crear pedido,sanctum')
        ->name('shipments.store');

    Route::match(['put', 'patch'], 'shipments/{shipment:tracking_number}', [ShipmentController::class, 'update'])
        ->middleware('permission:editar pedido,sanctum')
        ->name('shipments.update');

    Route::delete('shipments/{shipment:tracking_number}', [ShipmentController::class, 'destroy'])
        ->middleware('permission:eliminar pedido,sanctum')
        ->name('shipments.destroy');
});
