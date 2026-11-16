<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EstadoComprobante;
use App\Models\Comprobante;
use App\Services\Facturas\GeneradorDePdf;
use Symfony\Component\HttpFoundation\Response;

/** El PDF de una factura autorizada. Un borrador no tiene CAE ni QR: no hay PDF. */
class ComprobantePdfController extends Controller
{
    public function show(Comprobante $comprobante, GeneradorDePdf $generador): Response
    {
        $this->authorize('view', $comprobante);
        abort_unless($comprobante->estado === EstadoComprobante::Autorizado, 404);

        return $generador->generar($comprobante)->download($generador->nombreDeArchivo($comprobante));
    }
}
