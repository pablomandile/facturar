<?php

declare(strict_types=1);

namespace App\Exceptions\Arca;

/**
 * ARCA contestó con un SOAP Fault. WSAA informa así todos sus errores
 * (ns1:coe.alreadyAuthenticated, ns1:cms.cert.expired...); WSFE casi nunca,
 * porque devuelve sus errores dentro de la respuesta.
 */
class FaultDeArca extends ExcepcionDeArca
{
    /** El código sin el prefijo de namespace: coe.alreadyAuthenticated. */
    public readonly string $codigo;

    public function __construct(string $codigo, string $mensaje)
    {
        $this->codigo = (string) preg_replace('/^[^:]+:/', '', $codigo);

        parent::__construct($mensaje);
    }
}
