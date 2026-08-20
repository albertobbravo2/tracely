<?php

use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\ShipmentHistoryController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('shipments/{shipment:tracking_number}', [ShipmentController::class, 'show'])
    ->middleware('throttle:show-shipment')
    ->name('shipments.show');

// El grupo autentica con sanctum; cada ruta solo declara el permiso que exige.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('shipments', [ShipmentController::class, 'index'])
        ->middleware('permission:ver pedido')
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

    // Los documentos de un envío no tienen endpoint público: a diferencia del
    // seguimiento, contienen facturas y despachos de aduana.
    Route::get('documents', [DocumentController::class, 'index'])
        ->middleware('permission:ver documento')
        ->name('documents.index');

    Route::get('documents/{document}', [DocumentController::class, 'show'])
        ->middleware('permission:ver documento')
        ->name('documents.show');

    Route::get('documents/{document}/download', [DocumentController::class, 'download'])
        ->middleware('permission:ver documento')
        ->name('documents.download');

    Route::post('documents', [DocumentController::class, 'store'])
        ->middleware('permission:crear documento')
        ->name('documents.store');

    Route::match(['put', 'patch'], 'documents/{document}', [DocumentController::class, 'update'])
        ->middleware('permission:editar documento')
        ->name('documents.update');

    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])
        ->middleware('permission:eliminar documento')
        ->name('documents.destroy');

    Route::get('shipment-histories', [ShipmentHistoryController::class, 'index'])
        ->middleware('permission:ver historial de pedido')
        ->name('shipment-histories.index');

    Route::get('shipment-histories/{shipmentHistory}', [ShipmentHistoryController::class, 'show'])
        ->middleware('permission:ver historial de pedido')
        ->name('shipment-histories.show');

    Route::post('shipment-histories', [ShipmentHistoryController::class, 'store'])
        ->middleware('permission:crear historial de pedido')
        ->name('shipment-histories.store');

    Route::match(['put', 'patch'], 'shipment-histories/{shipmentHistory}', [ShipmentHistoryController::class, 'update'])
        ->middleware('permission:editar historial de pedido')
        ->name('shipment-histories.update');

    Route::delete('shipment-histories/{shipmentHistory}', [ShipmentHistoryController::class, 'destroy'])
        ->middleware('permission:eliminar historial de pedido')
        ->name('shipment-histories.destroy');

    Route::get('users', [UserController::class, 'index'])
        ->middleware('permission:ver usuario')
        ->name('users.index');

    Route::get('users/{user}', [UserController::class, 'show'])
        ->middleware('permission:ver usuario')
        ->name('users.show');


    Route::post('users', [UserController::class, 'store'])
        ->middleware('permission:crear usuario')
        ->name('users.store');

    Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])
        ->middleware('permission:editar usuario')
        ->name('users.update');

    Route::delete('users/{user}', [UserController::class, 'destroy'])
        ->middleware('permission:eliminar usuario')
        ->name('users.destroy');
});
