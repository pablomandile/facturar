<?php

declare(strict_types=1);

namespace App\Console\Commands\Arca;

use App\Enums\Arca\TipoComprobante;
use App\Services\Arca\ClienteWsfe;
use Illuminate\Console\Command;

/**
 * Las tablas de parámetros de ARCA que usa la app. Sirve para comparar los
 * códigos fijos de los enums contra lo que ARCA tiene hoy: si ARCA agrega o
 * da de baja un código, acá se ve.
 */
class ParametrosCommand extends Command
{
    use ResuelveEmisor;

    protected $signature = 'arca:parametros {emisor : id o CUIT}';

    protected $description = 'Lista tipos de comprobante, de documento, condiciones de IVA y puntos de venta según ARCA';

    public function handle(ClienteWsfe $wsfe): int
    {
        return $this->conArca(function () use ($wsfe): int {
            $emisor = $this->emisor();
            $this->encabezado($emisor);

            $tablas = [
                'Tipos de comprobante' => $wsfe->parametros($emisor, 'FEParamGetTiposCbte', 'CbteTipo'),
                'Tipos de documento' => $wsfe->parametros($emisor, 'FEParamGetTiposDoc', 'DocTipo'),
                'Conceptos' => $wsfe->parametros($emisor, 'FEParamGetTiposConcepto', 'ConceptoTipo'),
                'Condición de IVA del receptor (clase C)' => $wsfe->parametros($emisor, 'FEParamGetCondicionIvaReceptor', 'CondicionIvaReceptor', ['ClaseCmp' => TipoComprobante::FacturaC->clase()]),
            ];

            foreach ($tablas as $titulo => $filas) {
                $this->newLine();
                $this->line("<comment>{$titulo}</comment>");
                $this->table(['Código', 'Descripción'], array_map(null, array_keys($filas), array_values($filas)));
            }

            $puntos = $wsfe->puntosDeVenta($emisor);
            $this->newLine();
            $this->line('<comment>Puntos de venta Web Services en ARCA</comment>');
            $puntos === []
                ? $this->line('Ninguno (en homologación es lo normal).')
                : $this->table(['Número', 'Tipo', 'Bloqueado', 'Baja'], array_map(fn (array $p): array => [$p['numero'], $p['tipo'], $p['bloqueado'] ? 'sí' : 'no', $p['baja'] ?? ''], $puntos));

            return self::SUCCESS;
        });
    }
}
