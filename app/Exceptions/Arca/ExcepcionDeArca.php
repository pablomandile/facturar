<?php

declare(strict_types=1);

namespace App\Exceptions\Arca;

use RuntimeException;

/**
 * Base de todo lo que puede salir mal hablando con ARCA o preparando lo que
 * se le manda. El mensaje está pensado para mostrarse tal cual en un toast:
 * en español, con voseo y diciendo qué hacer.
 */
abstract class ExcepcionDeArca extends RuntimeException {}
