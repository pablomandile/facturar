<?php

declare(strict_types=1);

namespace App\Enums\Arca;

/**
 * Contra qué ARCA se habla.
 *
 * Homologación es el ambiente de pruebas: los comprobantes no tienen validez
 * fiscal, pero el CAE, la numeración y los rechazos se comportan igual que en
 * producción. Los dos ambientes **no comparten nada**: certificado, punto de
 * venta y numeración son de cada uno. Por eso todo lo que depende del
 * ambiente lo guarda como columna, en vez de leerlo del emisor al vuelo.
 */
enum Ambiente: string
{
    case Homologacion = 'homologacion';
    case Produccion = 'produccion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Homologacion => 'Homologación (pruebas)',
            self::Produccion => 'Producción',
        };
    }

    public function urlWsaa(): string
    {
        return config("arca.ambientes.{$this->value}.wsaa");
    }

    public function urlWsfe(): string
    {
        return config("arca.ambientes.{$this->value}.wsfe");
    }

    /**
     * El CN del emisor de los certificados que firma ARCA en este ambiente.
     * Sirve para avisar, al subir un certificado, que es del otro ambiente:
     * WSAA lo rechazaría con `cms.cert.untrusted` y el mensaje no lo dice.
     */
    public function autoridadCertificante(): string
    {
        return match ($this) {
            self::Homologacion => 'Computadores Test',
            self::Produccion => 'Computadores',
        };
    }
}
