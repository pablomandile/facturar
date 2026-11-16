<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\Arca\CertificadoInvalido;
use App\Http\Requests\CertificadoGenerarRequest;
use App\Http\Requests\CertificadoSubirRequest;
use App\Models\Certificado;
use App\Models\Emisor;
use App\Models\User;
use App\Services\Arca\GeneradorDeCertificados;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * El certificado de ARCA, en tres movimientos: generar el pedido (CSR),
 * bajarlo para subirlo a ARCA, y cargar el certificado que ARCA devuelve.
 * Todo para el ambiente en que está el emisor.
 */
class CertificadoController extends Controller
{
    public function __construct(private readonly GeneradorDeCertificados $generador) {}

    public function store(CertificadoGenerarRequest $peticion): RedirectResponse
    {
        $emisor = $this->emisorDe($peticion);
        $this->generador->generar($emisor, $emisor->ambiente, $peticion->string('alias')->toString());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Listo el pedido. Bajalo y subilo a ARCA con los pasos de abajo.',
        ]);

        return to_route('emisor.edit');
    }

    /** El CSR, como archivo para subir a ARCA. Es público: no lleva la clave. */
    public function csr(Request $peticion, Certificado $certificado): Response
    {
        $this->authorize('view', $certificado);

        return response($certificado->csr, 200, [
            'Content-Type' => 'application/pkcs10',
            'Content-Disposition' => 'attachment; filename="'.$certificado->nombreArchivoCsr().'"',
        ]);
    }

    public function update(CertificadoSubirRequest $peticion, Certificado $certificado): RedirectResponse
    {
        try {
            $this->generador->incorporar($certificado, $peticion->contenido());
        } catch (CertificadoInvalido $e) {
            // Va al campo que usó la persona, para que lo vea junto al formulario.
            $campo = $peticion->hasFile('archivo') ? 'archivo' : 'texto';

            throw ValidationException::withMessages([$campo => $e->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Certificado cargado. Ya podés probar la conexión con ARCA.',
        ]);

        return to_route('emisor.edit');
    }

    /** Descarta un pedido que todavía no recibió su certificado. */
    public function destroy(Certificado $certificado): RedirectResponse
    {
        $this->authorize('delete', $certificado);
        $certificado->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Se descartó el pedido.']);

        return to_route('emisor.edit');
    }

    private function emisorDe(Request $peticion): Emisor
    {
        $usuario = $peticion->user();
        assert($usuario instanceof User && $usuario->emisor !== null);

        return $usuario->emisor;
    }
}
