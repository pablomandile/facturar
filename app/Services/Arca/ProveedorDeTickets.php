<?php

declare(strict_types=1);

namespace App\Services\Arca;

use App\Enums\Arca\ServicioArca;
use App\Exceptions\Arca\ErrorDeWsaa;
use App\Exceptions\Arca\SinCertificadoVigente;
use App\Exceptions\Arca\TicketNoDisponible;
use App\Models\Certificado;
use App\Models\Emisor;
use App\Models\TicketDeAcceso;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Da un ticket de acceso que sirva para llamar a WSFE, pidiéndole uno nuevo
 * a WSAA solo cuando hace falta.
 *
 * La regla que manda: **ARCA no da un ticket nuevo mientras el anterior está
 * vigente** (contesta coe.alreadyAuthenticated). Por eso:
 *
 * - El ticket se guarda en la base, no en el cache, y la fila no se borra.
 * - Se renueva con margen, antes de que venza en medio de una emisión.
 * - Dos requests que lo necesitan a la vez no lo piden dos veces: el segundo
 *   espera el lock y, al entrar, encuentra el que trajo el primero.
 * - Si igual ARCA dice que ya hay uno (se perdió la fila, se restauró un
 *   backup), no se le insiste en cada request: se espera unos minutos.
 */
class ProveedorDeTickets
{
    /** Minutos sin volver a pedir cuando ARCA dice que ya hay uno vigente. */
    private const ESPERA_SI_YA_HAY_UNO = 10;

    public function __construct(private readonly ClienteWsaa $wsaa) {}

    public function obtener(Emisor $emisor, ServicioArca $servicio = ServicioArca::Wsfe): TicketDeAcceso
    {
        $certificado = $emisor->certificadoVigente() ?? throw SinCertificadoVigente::para($emisor->ambiente);
        $margen = (int) config('arca.margen_ticket_minutos');

        $ticket = $this->fila($emisor, $servicio);

        if ($ticket?->sirvePara($certificado, $margen)) {
            return $ticket;
        }

        $this->siEstaBloqueado($ticket, $certificado);

        $clave = "arca:ticket:{$emisor->id}:{$emisor->ambiente->value}:{$servicio->value}";

        try {
            return Cache::lock($clave, 60)->block(20, function () use ($emisor, $servicio, $certificado, $margen): TicketDeAcceso {
                // Otro request pudo haberlo renovado mientras se esperaba el lock.
                $ticket = $this->fila($emisor, $servicio);

                if ($ticket?->sirvePara($certificado, $margen)) {
                    return $ticket;
                }

                $this->siEstaBloqueado($ticket, $certificado);

                return $this->pedirNuevo($emisor, $servicio, $certificado);
            });
        } catch (LockTimeoutException) {
            throw TicketNoDisponible::ocupado();
        }
    }

    private function pedirNuevo(Emisor $emisor, ServicioArca $servicio, Certificado $certificado): TicketDeAcceso
    {
        $claves = ['emisor_id' => $emisor->id, 'ambiente' => $emisor->ambiente, 'servicio' => $servicio];

        try {
            $obtenido = $this->wsaa->login($certificado, $servicio);
        } catch (ErrorDeWsaa $error) {
            if ($error->yaHayUnTicketVigente()) {
                $hasta = now()->addMinutes(self::ESPERA_SI_YA_HAY_UNO);

                TicketDeAcceso::query()->updateOrCreate($claves, [
                    'certificado_id' => $certificado->id,
                    'bloqueado_hasta' => $hasta,
                ]);

                throw TicketNoDisponible::bloqueadoHasta($hasta);
            }

            throw $error;
        }

        return TicketDeAcceso::query()->updateOrCreate($claves, [
            'certificado_id' => $certificado->id,
            'token' => $obtenido->token,
            'sign' => $obtenido->sign,
            'unique_id' => $obtenido->uniqueId,
            'generado_en' => $obtenido->generadoEn,
            'expira_en' => $obtenido->expiraEn,
            'bloqueado_hasta' => null,
        ]);
    }

    private function fila(Emisor $emisor, ServicioArca $servicio): ?TicketDeAcceso
    {
        return TicketDeAcceso::query()
            ->where('emisor_id', $emisor->id)
            ->where('ambiente', $emisor->ambiente->value)
            ->where('servicio', $servicio->value)
            ->first();
    }

    private function siEstaBloqueado(?TicketDeAcceso $ticket, Certificado $certificado): void
    {
        if ($ticket?->estaBloqueado($certificado) && $ticket->bloqueado_hasta !== null) {
            throw TicketNoDisponible::bloqueadoHasta($ticket->bloqueado_hasta);
        }
    }
}
