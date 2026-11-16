<?php

declare(strict_types=1);

namespace App\Exceptions\Arca;

/**
 * El certificado que subió la persona no sirve: no es un certificado, no
 * corresponde a la clave que generó la app, es de otro CUIT o de otro
 * ambiente, o ya venció.
 */
class CertificadoInvalido extends ExcepcionDeArca {}
