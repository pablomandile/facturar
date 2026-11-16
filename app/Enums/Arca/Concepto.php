<?php

declare(strict_types=1);

namespace App\Enums\Arca;

/**
 * Qué se factura, con el código de ARCA (`Concepto`). Con servicios, ARCA
 * pide además el período facturado y el vencimiento del pago, y acepta una
 * fecha de comprobante más alejada de hoy.
 */
enum Concepto: int
{
    case Productos = 1;
    case Servicios = 2;
    case ProductosYServicios = 3;

    public function etiqueta(): string
    {
        return match ($this) {
            self::Productos => 'Productos',
            self::Servicios => 'Servicios',
            self::ProductosYServicios => 'Productos y servicios',
        };
    }

    public function exigeFechasDeServicio(): bool
    {
        return $this !== self::Productos;
    }

    /**
     * Cuántos días antes o después de hoy puede ser la fecha del
     * comprobante: 5 para productos, 10 si hay servicios.
     */
    public function ventanaDias(): int
    {
        return $this === self::Productos ? 5 : 10;
    }
}
