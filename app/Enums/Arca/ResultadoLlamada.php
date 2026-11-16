<?php

declare(strict_types=1);

namespace App\Enums\Arca;

/** Cómo terminó una llamada a ARCA, para el registro de llamadas. */
enum ResultadoLlamada: string
{
    /** Respondió sin errores (un rechazo de comprobante también es Ok: ARCA contestó). */
    case Ok = 'ok';

    /** Respondió con errores en el cuerpo (`Errors` de WSFE). */
    case ErrorDeArca = 'error_de_arca';

    /** Respondió con un SOAP Fault (lo típico de WSAA). */
    case Fault = 'fault';

    /** Se mandó y no llegó respuesta a tiempo: puede haberse procesado. */
    case Timeout = 'timeout';

    /** No se pudo ni conectar: seguro que no se procesó. */
    case SinConexion = 'sin_conexion';
}
