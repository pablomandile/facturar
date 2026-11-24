<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Arca\Ambiente;
use App\Models\Emisor;
use App\Models\User;
use App\Rules\CuitValido;
use App\Support\Arca\Cuit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * El perfil fiscal: alta la primera vez, edición después. El mismo request
 * para los dos, con una sola lista de campos (ver `campos()`).
 */
class EmisorGuardarRequest extends FormRequest
{
    public function authorize(): bool
    {
        $emisor = $this->usuario()->emisor;

        return $emisor === null || $this->usuario()->can('update', $emisor);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('cuit'))) {
            $this->merge(['cuit' => Cuit::normalizar($this->input('cuit'))]);
        }

        // Se guarda ya como va en el archivo; vacío vuelve a la sugerencia.
        if (is_string($this->input('nombre_archivos'))) {
            $this->merge(['nombre_archivos' => Emisor::normalizarNombreDeArchivo($this->input('nombre_archivos')) ?: null]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $emisor = $this->usuario()->emisor;

        return [
            'cuit' => ['required', 'string', new CuitValido, Rule::unique('emisores', 'cuit')->ignore($emisor?->id)],
            'razon_social' => ['required', 'string', 'max:120'],
            'nombre_archivos' => ['nullable', 'string', 'max:60'],
            'domicilio_fiscal' => ['required', 'string', 'max:200'],
            'ingresos_brutos' => ['nullable', 'string', 'max:30'],
            'inicio_actividades' => ['required', 'date', 'before_or_equal:today'],
            'ambiente' => ['required', Rule::enum(Ambiente::class)],
        ];
    }

    /**
     * El CUIT va adentro de cada certificado: cambiarlo con certificados
     * cargados los dejaría a todos inservibles sin que nada lo avise.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $emisor = $this->usuario()->emisor;

                if ($emisor !== null
                    && $emisor->cuit !== $this->input('cuit')
                    && $emisor->certificados()->exists()) {
                    $validator->errors()->add('cuit', 'No se puede cambiar el CUIT: ya tiene certificados de ARCA cargados.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'cuit' => 'CUIT',
            'razon_social' => 'nombre o razón social',
            'nombre_archivos' => 'nombre para los archivos',
            'domicilio_fiscal' => 'domicilio fiscal',
            'ingresos_brutos' => 'ingresos brutos',
            'inicio_actividades' => 'inicio de actividades',
        ];
    }

    /** @return array<string, mixed> */
    public function campos(): array
    {
        return $this->safe()->only(['cuit', 'razon_social', 'nombre_archivos', 'domicilio_fiscal', 'ingresos_brutos', 'inicio_actividades', 'ambiente']);
    }

    private function usuario(): User
    {
        $usuario = $this->user();
        assert($usuario instanceof User);

        return $usuario;
    }
}
