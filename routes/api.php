<?php

use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ShipmentController;
use Illuminate\Support\Facades\Route;

Route::get('shipments/{shipment:tracking_number}', [ShipmentController::class, 'show'])
    ->middleware('throttle:show-shipment')
    ->name('shipments.show');

// El grupo autentica con sanctum; cada ruta solo declara el permiso que exige.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('shipments', [ShipmentController::class, 'index'])
        ->name('shipments.index');

    Route::post('shipments', [ShipmentController::class, 'store'])
        ->middleware('permission:crear pedido')
        ->name('shipments.store');

    Route::match(['put', 'patch'], 'shipments/{shipment:tracking_number}', [ShipmentController::class, 'update'])
        ->middleware('permission:editar pedido')
        ->name('shipments.update');

    Route::delete('shipments/{shipment:tracking_number}', [ShipmentController::class, 'destroy'])
        ->middleware('permission:eliminar pedido')
        ->name('shipments.destroy');

    // Las empresas no tienen endpoint público: gestionarlas es cosa del
    // superadministrador, que es el único rol con estos permisos.
    Route::get('companies', [CompanyController::class, 'index'])
        ->middleware('permission:ver empresas')
        ->name('companies.index');

    Route::get('companies/{company}', [CompanyController::class, 'show'])
        ->middleware('permission:ver empresas')
        ->name('companies.show');

    Route::post('companies', [CompanyController::class, 'store'])
        ->middleware('permission:crear empresa')
        ->name('companies.store');

    Route::match(['put', 'patch'], 'companies/{company}', [CompanyController::class, 'update'])
        ->middleware('permission:editar empresa')
        ->name('companies.update');

    Route::delete('companies/{company}', [CompanyController::class, 'destroy'])
        ->middleware('permission:eliminar empresa')
        ->name('companies.destroy');
});
