<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EstadoComprobante;
use App\Models\Comprobante;
use App\Models\User;
use App\Services\Arca\RelojFiscal;
use App\Support\Dinero;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El panel: qué falta configurar, cuánto se facturó en el mes, si el
 * certificado está por vencer y las últimas facturas.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $peticion): Response
    {
        $usuario = $peticion->user();
        assert($usuario instanceof User);
        $emisor = $usuario->emisor;

        if ($emisor === null) {
            return Inertia::render('Dashboard', ['configurado' => false, 'pasos' => ['perfil' => false, 'certificado' => false, 'punto_de_venta' => false]]);
        }

        $certificado = $emisor->certificadoVigente();
        $tienePunto = $emisor->puntosDeVenta()->where('ambiente', $emisor->ambiente->value)->where('activo', true)->exists();
        $pasos = ['perfil' => true, 'certificado' => $certificado !== null, 'punto_de_venta' => $tienePunto];

        $hoy = (new RelojFiscal)->hoy();
        $delAmbiente = Comprobante::query()->where('emisor_id', $emisor->id)->where('ambiente', $emisor->ambiente->value);
        $delMes = $delAmbiente->clone()
            ->where('estado', EstadoComprobante::Autorizado->value)
            ->whereBetween('fecha_emision', [$hoy->startOfMonth()->toDateString(), $hoy->endOfMonth()->toDateString()]);

        return Inertia::render('Dashboard', [
            'configurado' => ! in_array(false, $pasos, true),
            'pasos' => $pasos,
            'ambiente' => $emisor->ambiente->etiqueta(),
            'mes' => [
                'nombre' => $hoy->isoFormat('MMMM YYYY'),
                'total' => Dinero::formatear((int) $delMes->clone()->sum('importe_total')),
                'cantidad' => $delMes->clone()->count(),
            ],
            'certificado' => $certificado === null ? null : [
                'vence' => $certificado->valido_hasta?->toDateString(),
                'dias' => $certificado->diasParaVencer(),
            ],
            'atencion' => [
                'pendientes' => $delAmbiente->clone()->where('estado', EstadoComprobante::Pendiente->value)->count(),
                'con_problemas' => $delAmbiente->clone()->whereIn('estado', [EstadoComprobante::Rechazado->value, EstadoComprobante::Error->value])->count(),
            ],
            'ultimas' => $delAmbiente->clone()
                ->with('puntoDeVenta')
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (Comprobante $c): array => [
                    'id' => $c->id,
                    'numero' => $c->numeroCompleto(),
                    'fecha' => $c->fecha_emision->toDateString(),
                    'receptor' => $c->receptor_nombre,
                    'total' => Dinero::formatear($c->importe_total),
                    'estado' => $c->estado->value,
                    'estado_etiqueta' => $c->estado->etiqueta(),
                ])
                ->all(),
        ]);
    }
}
