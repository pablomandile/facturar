<?php

declare(strict_types=1);

namespace App\Exceptions\Facturas;

use RuntimeException;

/**
 * Un envío que no se puede hacer: la factura no está autorizada, o es de
 * homologación y la dirección no es la de la cuenta. El FormRequest ya lo
 * frena; esto es la última defensa, para los caminos que no pasan por él.
 */
class EnvioNoPermitido extends RuntimeException {}
