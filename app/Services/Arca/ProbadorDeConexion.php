<?php

declare(strict_types=1);

namespace App\Services\Arca;

use App\Enums\Arca\TipoComprobante;
use App\Exceptions\Arca\ExcepcionDeArca;
use App\Models\Emisor;
use App\Models\PuntoDeVenta;
use App\Support\Arca\ChequeoDeEntorno;

/**
 * El botón "Probar la conexión": recorre, en orden, todo lo que tiene que
 * andar para facturar y dice dónde se corta. Cada paso depende del
 * anterior: sin servidores no hay ticket, sin ticket no hay numeración.
 */
class ProbadorDeConexion
{
    public function __construct(
        private readonly ClienteWsfe $wsfe,
        private readonly ProveedorDeTickets $tickets,
    ) {}

    /** @return list<ChequeoDeEntorno> */
    public function probar(Emisor $emisor): array
    {
        $pasos = [];

        try {
            $estado = $this->wsfe->dummy($emisor);
            $pasos[] = $estado->todoOk()
                ? ChequeoDeEntorno::bien('Servidores de ARCA', 'Responden.')
                : ChequeoDeEntorno::mal('Servidores de ARCA', 'ARCA informa problemas en sus servidores. Probá más tarde.');
        } catch (ExcepcionDeArca $e) {
            return [ChequeoDeEntorno::mal('Servidores de ARCA', $e->getMessage())];
        }

        try {
            $ticket = $this->tickets->obtener($emisor);
            $pasos[] = ChequeoDeEntorno::bien('Certificado y permiso para facturar', 'ARCA aceptó el certificado y dio permiso hasta las '
                .$ticket->expira_en?->setTimezone(RelojFiscal::ZONA)->format('H:i').'.');
        } catch (ExcepcionDeArca $e) {
            $pasos[] = ChequeoDeEntorno::mal('Certificado y permiso para facturar', $e->getMessage());

            return $pasos;
        }

        $puntos = $emisor->puntosDeVenta()
            ->where('ambiente', $emisor->ambiente->value)
            ->where('activo', true)
            ->orderBy('numero')
            ->get();

        if ($puntos->isEmpty()) {
            $pasos[] = ChequeoDeEntorno::mal('Punto de venta', 'Cargá al menos un punto de venta para poder facturar.');

            return $pasos;
        }

        foreach ($puntos as $punto) {
            $pasos[] = $this->probarPunto($emisor, $punto);
        }

        return $pasos;
    }

    private function probarPunto(Emisor $emisor, PuntoDeVenta $punto): ChequeoDeEntorno
    {
        $nombre = "Punto de venta {$punto->numeroFormateado()}";

        try {
            $ultimo = $this->wsfe->ultimoAutorizado($emisor, $punto->numero, TipoComprobante::FacturaC);

            return ChequeoDeEntorno::bien($nombre, $ultimo === 0
                ? 'Listo para la primera factura C.'
                : "Última factura C: {$ultimo}. La próxima va a ser la ".($ultimo + 1).'.');
        } catch (ExcepcionDeArca $e) {
            return ChequeoDeEntorno::mal($nombre, $e->getMessage());
        }
    }
}
