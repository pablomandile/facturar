<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PuntoDeVenta;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de un punto de venta. El número tiene que ser el mismo
 * que se dio de alta en ARCA, en el mismo ambiente en que está el emisor.
 */
class PuntoDeVentaGuardarRequest extends FormRequest
{
    public function authorize(): bool
    {
        $puntoDeVenta = $this->route('puntoDeVenta');

        if ($puntoDeVenta instanceof PuntoDeVenta) {
            return $this->user()?->can('update', $puntoDeVenta) ?? false;
        }

        return $this->usuario()->emisor !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['activo' => $this->boolean('activo', true)]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $puntoDeVenta = $this->route('puntoDeVenta');
        $emisor = $puntoDeVenta instanceof PuntoDeVenta ? $puntoDeVenta->emisor : $this->usuario()->emisor;
        $ambiente = $puntoDeVenta instanceof PuntoDeVenta ? $puntoDeVenta->ambiente : $emisor?->ambiente;

        return [
            'numero' => [
                'required', 'integer', 'between:1,99998',
                Rule::unique('puntos_de_venta', 'numero')
                    ->where('emisor_id', $emisor?->id)
                    ->where('ambiente', $ambiente?->value)
                    ->ignore($puntoDeVenta instanceof PuntoDeVenta ? $puntoDeVenta->id : null),
            ],
            'descripcion' => ['nullable', 'string', 'max:100'],
            'activo' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['numero.unique' => 'Ya cargaste ese punto de venta.'];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['numero' => 'número de punto de venta', 'descripcion' => 'descripción'];
    }

    private function usuario(): User
    {
        $usuario = $this->user();
        assert($usuario instanceof User);

        return $usuario;
    }
}
