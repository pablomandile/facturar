<?php

declare(strict_types=1);

namespace App\Exceptions\Arca;

/**
 * No hubo respuesta de ARCA. Lo que importa es si el pedido **pudo haber
 * llegado**: si no se pudo ni conectar, seguro que ARCA no lo procesó; si se
 * mandó y se cortó esperando la respuesta, una factura pudo haber quedado
 * autorizada sin que lo sepamos, y hay que consultarla antes de reintentar.
 */
class FallaDeConexion extends ExcepcionDeArca
{
    public function __construct(
        public readonly string $mensajeTecnico,
        public readonly bool $pudoHaberLlegado,
    ) {
        parent::__construct(
            $pudoHaberLlegado
                ? 'ARCA no respondió a tiempo. Puede que haya recibido el pedido: lo verificamos antes de volver a intentar.'
                : 'No se pudo conectar con ARCA. Probá de nuevo en unos minutos.',
        );
    }
}
