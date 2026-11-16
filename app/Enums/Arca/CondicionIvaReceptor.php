<?php

declare(strict_types=1);

namespace App\Enums\Arca;

/**
 * La condición frente al IVA del receptor, con el código de ARCA
 * (`CondicionIVAReceptorId`, obligatorio en WSFEv1 desde el 1/12/2026).
 *
 * Son los que ARCA acepta para comprobantes clase C
 * (FEParamGetCondicionIvaReceptor con ClaseCmp=C), menos los del exterior y
 * el IVA liberado de Tierra del Fuego, que la v1 no cubre. `arca:parametros`
 * muestra la tabla vigente para compararla.
 */
enum CondicionIvaReceptor: int
{
    case ResponsableInscripto = 1;
    case Exento = 4;
    case ConsumidorFinal = 5;
    case Monotributista = 6;
    case NoCategorizado = 7;
    case MonotributistaSocial = 13;
    case NoAlcanzado = 15;
    case MonotributoPromovido = 16;

    public function etiqueta(): string
    {
        return match ($this) {
            self::ResponsableInscripto => 'IVA Responsable Inscripto',
            self::Exento => 'IVA Sujeto Exento',
            self::ConsumidorFinal => 'Consumidor Final',
            self::Monotributista => 'Responsable Monotributo',
            self::NoCategorizado => 'Sujeto No Categorizado',
            self::MonotributistaSocial => 'Monotributista Social',
            self::NoAlcanzado => 'IVA No Alcanzado',
            self::MonotributoPromovido => 'Monotributo Trabajador Independiente Promovido',
        };
    }

    /**
     * Las condiciones de un inscripto en ARCA solo se pueden informar con
     * CUIT: un responsable inscripto o un monotributista identificado con
     * DNI es un rechazo seguro.
     */
    public function exigeCuit(): bool
    {
        return in_array($this, [
            self::ResponsableInscripto,
            self::Exento,
            self::Monotributista,
            self::MonotributistaSocial,
            self::MonotributoPromovido,
        ], true);
    }
}
