<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Comprobante;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * ARCA autorizó un comprobante. Lo dispara solo `EmisorDeComprobantes`, en
 * el único lugar por donde pasa toda autorización: la directa, la que sale
 * de consultar tras un corte y la del reconciliador de pendientes.
 */
class ComprobanteAutorizado
{
    use Dispatchable;

    public function __construct(public readonly Comprobante $comprobante) {}
}
