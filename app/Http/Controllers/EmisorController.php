<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Arca\Ambiente;
use App\Http\Requests\EmisorGuardarRequest;
use App\Http\Requests\PlantillaEmailRequest;
use App\Models\Certificado;
use App\Models\Emisor;
use App\Models\PuntoDeVenta;
use App\Models\User;
use App\Services\Facturas\TextoDelEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La configuración fiscal de la cuenta, en una sola pantalla con pasos:
 * datos del emisor, certificado de ARCA y puntos de venta. Cada paso se
 * puede retomar: lo que ya está hecho queda guardado.
 */
class EmisorController extends Controller
{
    public function edit(Request $peticion): Response
    {
        $usuario = $peticion->user();
        assert($usuario instanceof User);
        $emisor = $usuario->emisor;

        $vigente = $emisor?->certificadoVigente();
        $pendiente = $emisor?->certificadoPendiente();
        $puntosDeVenta = $emisor === null ? collect() : $emisor->puntosDeVenta()
            ->where('ambiente', $emisor->ambiente->value)
            ->orderBy('numero')
            ->get();

        return Inertia::render('emisor/Configuracion', [
            'emisor' => $emisor === null ? null : $this->serializarEmisor($emisor),
            'ambientes' => array_map(
                fn (Ambiente $ambiente): array => ['value' => $ambiente->value, 'etiqueta' => $ambiente->etiqueta()],
                Ambiente::cases(),
            ),
            'certificadoVigente' => $vigente === null ? null : $this->serializarCertificado($vigente),
            'certificadoPendiente' => $pendiente === null ? null : $this->serializarCertificado($pendiente),
            'puntosDeVenta' => $puntosDeVenta->map(fn (PuntoDeVenta $punto): array => [
                'id' => $punto->id,
                'numero' => $punto->numero,
                'numero_formateado' => $punto->numeroFormateado(),
                'descripcion' => $punto->descripcion,
                'activo' => $punto->activo,
            ])->all(),
            'email' => [
                'plantilla' => $emisor?->plantilla_email,
                'por_defecto' => TextoDelEmail::POR_DEFECTO,
                'marcadores' => TextoDelEmail::MARCADORES,
                'maximo' => TextoDelEmail::PLANTILLA_MAXIMA,
            ],
            'pasos' => [
                'perfil' => $emisor !== null,
                'certificado' => $vigente !== null,
                'punto_de_venta' => $puntosDeVenta->contains('activo', true),
            ],
        ]);
    }

    public function update(EmisorGuardarRequest $peticion): RedirectResponse
    {
        $usuario = $peticion->user();
        assert($usuario instanceof User);

        $esAlta = $usuario->emisor === null;
        $usuario->emisor()->updateOrCreate([], $peticion->campos());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $esAlta ? 'Guardamos tus datos fiscales. Ahora vamos con el certificado.' : 'Se guardaron los cambios.',
        ]);

        return to_route('emisor.edit');
    }

    /** El texto del email de las facturas (ver TextoDelEmail). */
    public function plantillaEmail(PlantillaEmailRequest $peticion): RedirectResponse
    {
        $usuario = $peticion->user();
        assert($usuario instanceof User && $usuario->emisor !== null);

        $usuario->emisor->forceFill(['plantilla_email' => $peticion->plantilla()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Se guardó el texto del email.']);

        return to_route('emisor.edit');
    }

    /** @return array<string, mixed> */
    private function serializarEmisor(Emisor $emisor): array
    {
        return [
            'cuit' => $emisor->cuit,
            'cuit_formateado' => $emisor->cuitFormateado(),
            'razon_social' => $emisor->razon_social,
            'domicilio_fiscal' => $emisor->domicilio_fiscal,
            'ingresos_brutos' => $emisor->ingresos_brutos,
            'inicio_actividades' => $emisor->inicio_actividades->toDateString(),
            'ambiente' => $emisor->ambiente->value,
            'ambiente_etiqueta' => $emisor->ambiente->etiqueta(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarCertificado(Certificado $certificado): array
    {
        return [
            'id' => $certificado->id,
            'alias' => $certificado->alias,
            'estado' => $certificado->estado->value,
            'estado_etiqueta' => $certificado->estado->etiqueta(),
            'generado_el' => $certificado->created_at?->toDateString(),
            'valido_desde' => $certificado->valido_desde?->toDateString(),
            'valido_hasta' => $certificado->valido_hasta?->toDateString(),
            'dias_para_vencer' => $certificado->diasParaVencer(),
        ];
    }
}
