<?php

declare(strict_types=1);

namespace App\Exceptions\Arca;

use App\Support\Arca\ObservacionDeArca;

/**
 * WSFE contestó con errores en el cuerpo de la respuesta (Errors). No es un
 * rechazo de comprobante (eso viene en Observaciones, con Resultado R): es
 * que el pedido mismo no se pudo procesar.
 */
class ErrorDeWsfe extends ExcepcionDeArca
{
    /** @param  list<ObservacionDeArca>  $errores */
    public function __construct(public readonly array $errores)
    {
        parent::__construct('ARCA devolvió un error: '.implode(' · ', array_map(
            fn (ObservacionDeArca $error): string => (string) $error,
            $errores,
        )));
    }

    public function tieneCodigo(int $codigo): bool
    {
        foreach ($this->errores as $error) {
            if ($error->codigo === $codigo) {
                return true;
            }
        }

        return false;
    }

    public function primerCodigo(): ?int
    {
        return $this->errores[0]->codigo ?? null;
    }
}
