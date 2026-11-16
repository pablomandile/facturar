<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoComprobante;
use App\Models\Certificado;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\ComprobanteItem;
use App\Models\PuntoDeVenta;
use App\Models\User;
use App\Services\Facturas\GeneradorDePdf;
use App\Support\Dinero;
use RuntimeException;
use ZipArchive;

/**
 * "Descargar mis datos": un ZIP con todo lo de la cuenta en `datos.json` y el
 * PDF de cada factura autorizada en `facturas/`.
 *
 * Es el derecho de acceso de la política de privacidad, y además la forma de
 * conservar los comprobantes antes de eliminar la cuenta (que los borra).
 *
 * Lo que **no** va: la clave privada del certificado y el permiso de ARCA. La
 * política dice que la clave no se descarga nunca.
 */
class ExportadorDeDatos
{
    public function __construct(private readonly GeneradorDePdf $pdf) {}

    /** Arma el ZIP en un temporal y devuelve su ruta. Lo borra quien lo envía. */
    public function generar(User $usuario): string
    {
        $directorio = storage_path('app/private/exportaciones');

        if (! is_dir($directorio) && ! mkdir($directorio, 0700, true) && ! is_dir($directorio)) {
            throw new RuntimeException("No se pudo crear {$directorio}");
        }

        $ruta = $directorio.'/'.bin2hex(random_bytes(16)).'.zip';
        $zip = new ZipArchive;

        if ($zip->open($ruta, ZipArchive::CREATE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo de exportación');
        }

        $zip->addFromString('datos.json', (string) json_encode(
            $this->datos($usuario),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));

        $emisor = $usuario->emisor;
        if ($emisor !== null) {
            $autorizados = Comprobante::query()
                ->with(['emisor', 'puntoDeVenta', 'items'])
                ->where('emisor_id', $emisor->id)
                ->where('estado', EstadoComprobante::Autorizado->value)
                ->orderBy('id')
                ->cursor();

            foreach ($autorizados as $comprobante) {
                $carpeta = $comprobante->ambiente->value === 'produccion' ? 'facturas' : 'facturas-de-prueba';
                $zip->addFromString("{$carpeta}/{$this->pdf->nombreDeArchivo($comprobante)}", $this->pdf->generar($comprobante)->output());
            }
        }

        $zip->close();

        return $ruta;
    }

    /** @return array<string, mixed> */
    public function datos(User $usuario): array
    {
        $emisor = $usuario->emisor;

        return [
            'exportado_en' => now()->toIso8601String(),
            'cuenta' => [
                'nombre' => $usuario->name,
                'email' => $usuario->email,
                'creada_en' => $usuario->created_at?->toIso8601String(),
                'terminos_version' => $usuario->terminos_version,
                'terminos_aceptados_en' => $usuario->terminos_aceptados_en?->toIso8601String(),
            ],
            'emisor' => $emisor === null ? null : [
                'cuit' => $emisor->cuit,
                'razon_social' => $emisor->razon_social,
                'domicilio_fiscal' => $emisor->domicilio_fiscal,
                'ingresos_brutos' => $emisor->ingresos_brutos,
                'inicio_actividades' => $emisor->inicio_actividades->toDateString(),
                'ambiente' => $emisor->ambiente->value,
            ],
            'certificados' => $emisor === null ? [] : $emisor->certificados()->orderBy('id')->get()->map(fn (Certificado $c): array => [
                'alias' => $c->alias,
                'ambiente' => $c->ambiente->value,
                'estado' => $c->estado->value,
                'valido_desde' => $c->valido_desde?->toIso8601String(),
                'valido_hasta' => $c->valido_hasta?->toIso8601String(),
                'certificado' => $c->certificado,
            ])->all(),
            'puntos_de_venta' => $emisor === null ? [] : $emisor->puntosDeVenta()->orderBy('numero')->get()->map(fn (PuntoDeVenta $p): array => [
                'numero' => $p->numero,
                'ambiente' => $p->ambiente->value,
                'descripcion' => $p->descripcion,
                'activo' => $p->activo,
            ])->all(),
            'clientes' => $emisor === null ? [] : Cliente::query()->where('emisor_id', $emisor->id)->orderBy('nombre')->get()->map(fn (Cliente $c): array => [
                'nombre' => $c->nombre,
                'tipo_documento' => $c->tipo_documento->etiqueta(),
                'numero_documento' => $c->numero_documento,
                'condicion_iva' => $c->condicion_iva->etiqueta(),
                'domicilio' => $c->domicilio,
                'email' => $c->email,
            ])->all(),
            'comprobantes' => $emisor === null ? [] : Comprobante::query()
                ->with(['puntoDeVenta', 'items'])
                ->where('emisor_id', $emisor->id)
                ->orderBy('id')
                ->get()
                ->map(fn (Comprobante $c): array => [
                    'tipo' => $c->tipo->etiqueta(),
                    'numero' => $c->numero === null ? null : $c->numeroCompleto(),
                    'ambiente' => $c->ambiente->value,
                    'estado' => $c->estado->value,
                    'concepto' => $c->concepto->etiqueta(),
                    'fecha_emision' => $c->fecha_emision->toDateString(),
                    'periodo' => $c->fecha_servicio_desde === null ? null : [
                        'desde' => $c->fecha_servicio_desde->toDateString(),
                        'hasta' => $c->fecha_servicio_hasta?->toDateString(),
                        'vencimiento_pago' => $c->fecha_vto_pago?->toDateString(),
                    ],
                    'receptor' => [
                        'nombre' => $c->receptor_nombre,
                        'tipo_documento' => $c->receptor_tipo_documento->etiqueta(),
                        'numero_documento' => $c->receptor_numero_documento,
                        'condicion_iva' => $c->receptor_condicion_iva->etiqueta(),
                        'domicilio' => $c->receptor_domicilio,
                    ],
                    'items' => $c->items->map(fn (ComprobanteItem $i): array => [
                        'descripcion' => $i->descripcion,
                        'cantidad' => $i->cantidad,
                        'unidad' => $i->unidad,
                        'precio_unitario' => Dinero::aDecimal($i->precio_unitario),
                        'importe' => Dinero::aDecimal($i->importe),
                    ])->all(),
                    'total' => Dinero::aDecimal($c->importe_total),
                    'cae' => $c->cae,
                    'cae_vencimiento' => $c->cae_vencimiento?->toDateString(),
                    'observaciones' => $c->observaciones,
                    'respuesta_arca' => $c->respuesta_arca,
                ])->all(),
        ];
    }
}
