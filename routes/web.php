<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\CertificadoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ComprobanteController;
use App\Http\Controllers\ComprobantePdfController;
use App\Http\Controllers\ConexionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmisorController;
use App\Http\Controllers\ExportacionController;
use App\Http\Controllers\LegalesController;
use App\Http\Controllers\PuntoDeVentaController;
use App\Http\Middleware\RequiereEmisor;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// La pantalla que muestra el service worker sin conexión. Blade suelto: se
// dibuja sin los assets compilados.
Route::view('offline', 'offline')->name('offline');

// Los textos legales se leen con y sin sesión.
Route::get('terminos', [LegalesController::class, 'terminos'])->name('terminos');
Route::get('privacidad', [LegalesController::class, 'privacidad'])->name('privacidad');
Route::inertia('cookies', 'Cookies')->name('cookies');

/*
 * Ingreso con Google, bajo /auth como las rutas de Fortify. `guest`: con la
 * sesión abierta, rehacer el flujo solo puede cambiarle la cuenta sin querer.
 */
Route::middleware('guest')->group(function () {
    Route::get('auth/google/redirect', [GoogleController::class, 'redirigir'])->name('google.redirect');
    Route::get('auth/google/callback', [GoogleController::class, 'volver'])->name('google.callback');
});

Route::middleware('auth')->group(function () {
    // La pantalla "aceptar y continuar" que muestra AceptoTerminos.
    Route::get('terminos/aceptar', [LegalesController::class, 'aceptar'])->name('terminos.aceptar');
    Route::post('terminos/aceptar', [LegalesController::class, 'guardarAceptacion'])->name('terminos.aceptar.store');

    // Derecho de acceso: no depende de aceptar los términos ni de verificar el email.
    Route::get('mis-datos', [ExportacionController::class, 'datos'])->middleware('throttle:3,1')->name('exportacion.datos');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Configuración fiscal: datos del emisor, certificado y puntos de venta.
    Route::get('emisor', [EmisorController::class, 'edit'])->name('emisor.edit');
    Route::put('emisor', [EmisorController::class, 'update'])->name('emisor.update');

    Route::post('emisor/certificados', [CertificadoController::class, 'store'])->name('certificados.store');
    Route::get('emisor/certificados/{certificado}/csr', [CertificadoController::class, 'csr'])->name('certificados.csr');
    // POST y no PUT: lleva un archivo, y PHP no parsea multipart en un PUT.
    Route::post('emisor/certificados/{certificado}', [CertificadoController::class, 'update'])->name('certificados.update');
    Route::delete('emisor/certificados/{certificado}', [CertificadoController::class, 'destroy'])->name('certificados.destroy');

    // Cada prueba pide hasta tres cosas a ARCA: con un límite alcanza.
    Route::post('emisor/conexion', [ConexionController::class, 'store'])->middleware('throttle:6,1')->name('conexion.store');

    Route::post('emisor/puntos-de-venta', [PuntoDeVentaController::class, 'store'])->name('puntos-de-venta.store');
    Route::put('emisor/puntos-de-venta/{puntoDeVenta}', [PuntoDeVentaController::class, 'update'])->name('puntos-de-venta.update');
    Route::delete('emisor/puntos-de-venta/{puntoDeVenta}', [PuntoDeVentaController::class, 'destroy'])->name('puntos-de-venta.destroy');

    // Todo lo que factura necesita los datos fiscales cargados.
    Route::middleware(RequiereEmisor::class)->group(function () {
        Route::get('clientes', [ClienteController::class, 'index'])->name('clientes.index');
        Route::post('clientes', [ClienteController::class, 'store'])->name('clientes.store');
        Route::put('clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
        Route::delete('clientes/{cliente}', [ClienteController::class, 'destroy'])->name('clientes.destroy');

        Route::get('comprobantes', [ComprobanteController::class, 'index'])->name('comprobantes.index');
        Route::get('comprobantes/nuevo', [ComprobanteController::class, 'create'])->name('comprobantes.create');
        Route::post('comprobantes', [ComprobanteController::class, 'store'])->name('comprobantes.store');
        Route::get('comprobantes/{comprobante}', [ComprobanteController::class, 'show'])->name('comprobantes.show');
        Route::get('comprobantes/{comprobante}/editar', [ComprobanteController::class, 'edit'])->name('comprobantes.edit');
        // GET: abre el formulario precargado, no guarda nada.
        Route::get('comprobantes/{comprobante}/duplicar', [ComprobanteController::class, 'duplicar'])->name('comprobantes.duplicar');
        Route::put('comprobantes/{comprobante}', [ComprobanteController::class, 'update'])->name('comprobantes.update');
        Route::post('comprobantes/{comprobante}/emitir', [ComprobanteController::class, 'emitir'])->name('comprobantes.emitir');
        Route::get('comprobantes/{comprobante}/pdf', [ComprobantePdfController::class, 'show'])->name('comprobantes.pdf');
        Route::delete('comprobantes/{comprobante}', [ComprobanteController::class, 'destroy'])->name('comprobantes.destroy');
    });
});

require __DIR__.'/settings.php';
