<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\Arca\Ambiente;
use App\Events\ComprobanteAutorizado;
use App\Models\Cliente;
use App\Services\Facturas\EnviadorDeFacturas;
use App\Services\Facturas\TextoDelEmail;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * La casilla "Enviarla al cliente cuando ARCA la autorice": manda la factura
 * al email del cliente, con copia a la cuenta y el texto de la plantilla.
 *
 * Nada de lo que pase acá puede frenar la autorización: el enviador ya
 * registra los fallos del correo sin lanzar, y lo demás se loguea.
 */
class EnviarAlAutorizar
{
    public function __construct(
        private readonly EnviadorDeFacturas $enviador,
        private readonly TextoDelEmail $texto,
    ) {}

    public function handle(ComprobanteAutorizado $evento): void
    {
        $comprobante = $evento->comprobante;

        if (! $comprobante->enviar_al_autorizar || $comprobante->ambiente !== Ambiente::Produccion) {
            return;
        }

        $email = $comprobante->cliente instanceof Cliente ? trim((string) $comprobante->cliente->email) : '';

        if ($email === '') {
            return;
        }

        try {
            $this->enviador->enviar($comprobante, [$email], true, $this->texto->para($comprobante));
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar la factura al autorizarla', ['comprobante' => $comprobante->id, 'error' => $e->getMessage()]);
        }
    }
}
