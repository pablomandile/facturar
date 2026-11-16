<?php

use App\Http\Controllers\CertificadoController;
use App\Http\Controllers\EmisorController;
use App\Http\Controllers\PuntoDeVentaController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Configuración fiscal: datos del emisor, certificado y puntos de venta.
    Route::get('emisor', [EmisorController::class, 'edit'])->name('emisor.edit');
    Route::put('emisor', [EmisorController::class, 'update'])->name('emisor.update');

    Route::post('emisor/certificados', [CertificadoController::class, 'store'])->name('certificados.store');
    Route::get('emisor/certificados/{certificado}/csr', [CertificadoController::class, 'csr'])->name('certificados.csr');
    // POST y no PUT: lleva un archivo, y PHP no parsea multipart en un PUT.
    Route::post('emisor/certificados/{certificado}', [CertificadoController::class, 'update'])->name('certificados.update');
    Route::delete('emisor/certificados/{certificado}', [CertificadoController::class, 'destroy'])->name('certificados.destroy');

    Route::post('emisor/puntos-de-venta', [PuntoDeVentaController::class, 'store'])->name('puntos-de-venta.store');
    Route::put('emisor/puntos-de-venta/{puntoDeVenta}', [PuntoDeVentaController::class, 'update'])->name('puntos-de-venta.update');
    Route::delete('emisor/puntos-de-venta/{puntoDeVenta}', [PuntoDeVentaController::class, 'destroy'])->name('puntos-de-venta.destroy');
});

require __DIR__.'/settings.php';
