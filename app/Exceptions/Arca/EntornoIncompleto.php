<?php

declare(strict_types=1);

namespace App\Exceptions\Arca;

/**
 * Al servidor le falta algo para hablar con ARCA: una extensión de PHP, la
 * configuración de OpenSSL o un directorio con permisos. No es culpa de la
 * persona que factura; `php artisan arca:diagnostico` dice qué.
 */
class EntornoIncompleto extends ExcepcionDeArca {}
