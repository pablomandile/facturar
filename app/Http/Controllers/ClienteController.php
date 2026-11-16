<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Arca\CondicionIvaReceptor;
use App\Enums\Arca\TipoDocumento;
use App\Http\Requests\ClienteGuardarRequest;
use App\Models\Cliente;
use App\Models\Emisor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los clientes del emisor. Alta y edición en un diálogo sobre el listado.
 */
class ClienteController extends Controller
{
    private const POR_PAGINA = 20;

    public function index(Request $peticion): Response
    {
        $emisor = $this->emisorDe($peticion);
        $busqueda = trim($peticion->string('q')->toString());

        $clientes = Cliente::query()
            ->where('emisor_id', $emisor->id)
            ->when($busqueda !== '', function ($consulta) use ($busqueda) {
                $digitos = preg_replace('/\D/', '', $busqueda);

                $consulta->where(fn ($q) => $q
                    ->where('nombre', 'like', "%{$busqueda}%")
                    ->when($digitos !== '', fn ($q) => $q->orWhere('numero_documento', 'like', "%{$digitos}%")));
            })
            ->orderBy('nombre')
            ->paginate(self::POR_PAGINA)
            ->withQueryString()
            ->through(fn (Cliente $cliente): array => $cliente->paraLista());

        return Inertia::render('clientes/Index', [
            'clientes' => $clientes,
            'busqueda' => $busqueda,
            'tiposDocumento' => array_map(
                fn (TipoDocumento $tipo): array => ['value' => $tipo->value, 'etiqueta' => $tipo->etiqueta()],
                TipoDocumento::deClientes(),
            ),
            'condicionesIva' => array_map(
                fn (CondicionIvaReceptor $condicion): array => [
                    'value' => $condicion->value,
                    'etiqueta' => $condicion->etiqueta(),
                    'exige_cuit' => $condicion->exigeCuit(),
                ],
                CondicionIvaReceptor::cases(),
            ),
        ]);
    }

    /**
     * Si el documento era de un cliente borrado, lo restaura con los datos
     * nuevos: el UNIQUE de la tabla incluye a los borrados.
     */
    public function store(ClienteGuardarRequest $peticion): RedirectResponse
    {
        $emisor = $this->emisorDe($peticion);
        $datos = $peticion->validated();

        $cliente = Cliente::withTrashed()->firstOrNew([
            'emisor_id' => $emisor->id,
            'tipo_documento' => $datos['tipo_documento'],
            'numero_documento' => $datos['numero_documento'],
        ]);
        $cliente->fill($datos);
        $cliente->emisor_id = $emisor->id;
        $cliente->deleted_at = null;
        $cliente->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Se agregó a {$cliente->nombre}."]);

        return back();
    }

    public function update(ClienteGuardarRequest $peticion, Cliente $cliente): RedirectResponse
    {
        $cliente->update($peticion->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Se guardaron los cambios.']);

        return back();
    }

    /** Las facturas ya emitidas guardan su propia copia del receptor: no se tocan. */
    public function destroy(Cliente $cliente): RedirectResponse
    {
        $this->authorize('delete', $cliente);
        $cliente->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Se borró a {$cliente->nombre}."]);

        return back();
    }

    private function emisorDe(Request $peticion): Emisor
    {
        $usuario = $peticion->user();
        assert($usuario instanceof User && $usuario->emisor !== null);

        return $usuario->emisor;
    }
}
