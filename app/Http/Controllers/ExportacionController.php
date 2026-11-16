<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Arca\RelojFiscal;
use App\Services\ExportadorDeDatos;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * "Descargar mis datos". No pide haber aceptado los términos (ver
 * AceptoTerminos::EXENTAS): llevarse los datos es un derecho.
 */
class ExportacionController extends Controller
{
    public function datos(Request $peticion, ExportadorDeDatos $exportador): BinaryFileResponse
    {
        $usuario = $peticion->user();
        assert($usuario instanceof User);

        // Un PDF por factura autorizada: con muchas, tarda.
        set_time_limit(300);

        $nombre = 'facturar-mis-datos-'.(new RelojFiscal)->hoy()->toDateString().'.zip';

        return response()->download($exportador->generar($usuario), $nombre)->deleteFileAfterSend();
    }
}
