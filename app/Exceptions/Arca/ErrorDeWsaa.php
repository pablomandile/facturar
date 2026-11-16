<?php

declare(strict_types=1);

namespace App\Exceptions\Arca;

/**
 * WSAA no entregó el ticket de acceso. El mensaje traduce el código de ARCA
 * a qué tiene que hacer la persona.
 */
class ErrorDeWsaa extends ExcepcionDeArca
{
    public function __construct(
        public readonly string $codigo,
        public readonly string $mensajeDeArca,
    ) {
        parent::__construct(self::explicar($codigo, $mensajeDeArca));
    }

    public static function desdeFault(FaultDeArca $fault): self
    {
        return new self($fault->codigo, $fault->getMessage());
    }

    /** ARCA tiene un ticket vigente para este certificado que no tenemos. */
    public function yaHayUnTicketVigente(): bool
    {
        return $this->codigo === 'coe.alreadyAuthenticated';
    }

    private static function explicar(string $codigo, string $mensajeDeArca): string
    {
        return match ($codigo) {
            'cms.cert.untrusted' => 'ARCA no reconoce el certificado. Revisá que sea del mismo ambiente en que estás facturando.',
            'cms.cert.expired' => 'El certificado venció. Generá un pedido nuevo en Datos fiscales y pedí otro en ARCA.',
            'cms.cert.notYetValid' => 'El certificado todavía no está vigente. Probá de nuevo en unos minutos.',
            'coe.notAuthorized' => 'El certificado no está autorizado para Facturación Electrónica. Falta asociarlo al servicio wsfe en ARCA (los pasos están en Datos fiscales).',
            'coe.alreadyAuthenticated' => 'ARCA tiene un permiso anterior todavía vigente que no pudimos recuperar. Se libera solo: probá de nuevo en unos minutos.',
            'xml.generationTime.invalid', 'xml.expirationTime.invalid' => 'La hora del servidor no coincide con la de ARCA. Avisanos para corregirla.',
            'cms.bad', 'cms.sign.invalid', 'cms.bad.base64' => 'ARCA no pudo validar la firma del pedido. Avisanos: es un problema nuestro.',
            default => "ARCA rechazó la autenticación: {$mensajeDeArca}",
        };
    }
}
