<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

// Backoffice: la gestión interna de pedidos, historial, usuarios, documentos y
// empresas. Un único `role:` para todo el bloque; la discriminación fina por
// sección la sigue haciendo la API, que es contra la que van estas pantallas
// (cada endpoint lleva su propio `permission:`, ver routes/api.php).
Route::middleware(['auth', 'role:agente|administrador|superadministrador'])
    ->prefix('backoffice')
    ->name('backoffice.')
    ->group(function () {
        Route::redirect('/', '/backoffice/shipments')->name('index');

        Route::livewire('shipments', 'backoffice.shipments')->name('shipments');
        Route::livewire('shipment-histories', 'backoffice.shipment-histories')->name('shipment-histories');
        Route::livewire('users', 'backoffice.users')->name('users');
        Route::livewire('documents', 'backoffice.documents')->name('documents');
        Route::livewire('companies', 'backoffice.companies')->name('companies');
    });

// settings
require __DIR__.'/settings.php';
