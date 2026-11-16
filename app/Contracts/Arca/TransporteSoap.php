<?php

declare(strict_types=1);

namespace App\Contracts\Arca;

use App\Exceptions\Arca\FallaDeConexion;
use App\Exceptions\Arca\FaultDeArca;

/**
 * El cable hacia un web service de ARCA. Todo lo que habla con ARCA pasa por
 * acá, y es lo único que los tests reemplazan: así se prueba todo lo demás
 * (armar pedidos, leer respuestas, manejar errores) sin red.
 */
interface TransporteSoap
{
    /**
     * Llama a un método del servicio y devuelve la respuesta tal como la
     * entrega SoapClient (stdClass anidados).
     *
     * @param  array<string, mixed>  $parametros
     *
     * @throws FallaDeConexion si no hubo respuesta
     * @throws FaultDeArca si ARCA contestó con un SOAP Fault
     */
    public function llamar(string $metodo, array $parametros = []): object;

    /** El XML del último pedido, para el registro de llamadas. */
    public function ultimoPedido(): ?string;

    /** El XML de la última respuesta, para el registro de llamadas. */
    public function ultimaRespuesta(): ?string;
}
