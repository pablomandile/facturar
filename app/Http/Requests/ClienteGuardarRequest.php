<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Arca\CondicionIvaReceptor;
use App\Enums\Arca\TipoDocumento;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de un cliente. El documento se valida con su dígito
 * verificador y contra la condición frente al IVA: ARCA rechaza un
 * responsable inscripto o un monotributista que no venga con CUIT.
 */
class ClienteGuardarRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cliente = $this->route('cliente');

        if ($cliente instanceof Cliente) {
            return $this->user()?->can('update', $cliente) ?? false;
        }

        $usuario = $this->user();

        return $usuario instanceof User && $usuario->emisor !== null;
    }

    protected function prepareForValidation(): void
    {
        $numero = $this->input('numero_documento');

        if (is_string($numero)) {
            $this->merge(['numero_documento' => preg_replace('/\D/', '', $numero)]);
        }

        foreach (['alias', 'domicilio', 'email'] as $campo) {
            if ($this->input($campo) === '') {
                $this->merge([$campo => null]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tipo_documento' => ['required', 'integer', Rule::in(array_map(fn (TipoDocumento $t): int => $t->value, TipoDocumento::deClientes()))],
            'numero_documento' => ['required', 'string', 'max:11'],
            'nombre' => ['required', 'string', 'max:120'],
            'alias' => ['nullable', 'string', 'max:60'],
            'condicion_iva' => ['required', Rule::enum(CondicionIvaReceptor::class)],
            'domicilio' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:255'],
            'plantilla_descripcion' => ['nullable', 'string', 'max:'.Cliente::PLANTILLA_MAXIMA],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $tipo = TipoDocumento::from($this->integer('tipo_documento'));
                $condicion = CondicionIvaReceptor::from($this->integer('condicion_iva'));
                $numero = (string) $this->input('numero_documento');

                if (! $tipo->numeroValido($numero)) {
                    $validator->errors()->add('numero_documento', $tipo === TipoDocumento::Dni
                        ? 'El DNI tiene que tener 7 u 8 números.'
                        : "El {$tipo->etiqueta()} no es válido: revisá los 11 números.");
                }

                if ($condicion->exigeCuit() && $tipo !== TipoDocumento::Cuit) {
                    $validator->errors()->add('condicion_iva', "Para {$condicion->etiqueta()} ARCA pide identificarlo con CUIT.");
                }

                if ($this->yaExiste($tipo, $numero)) {
                    $validator->errors()->add('numero_documento', 'Ya tenés un cliente con ese documento.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'tipo_documento' => 'tipo de documento',
            'numero_documento' => 'número de documento',
            'condicion_iva' => 'condición frente al IVA',
        ];
    }

    /** Otro cliente vivo del mismo emisor con el mismo documento. */
    private function yaExiste(TipoDocumento $tipo, string $numero): bool
    {
        $cliente = $this->route('cliente');
        $usuario = $this->user();
        $emisorId = $cliente instanceof Cliente ? $cliente->emisor_id : ($usuario instanceof User ? $usuario->emisor?->id : null);
        $propio = $cliente instanceof Cliente ? $cliente->id : null;

        return Cliente::query()
            ->where('emisor_id', $emisorId)
            ->where('tipo_documento', $tipo->value)
            ->where('numero_documento', $numero)
            ->when($propio !== null, fn ($consulta) => $consulta->whereKeyNot($propio))
            ->exists();
    }
}
