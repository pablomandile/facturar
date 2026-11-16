<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Generar la clave y el pedido de certificado. Solo pide el alias: el
 * "nombre simbólico" con que ARCA lista el certificado en sus pantallas.
 */
class CertificadoGenerarRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        return $usuario instanceof User && $usuario->emisor !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('alias'))) {
            $this->merge(['alias' => mb_strtolower(trim($this->input('alias')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // ARCA acepta letras y números en el nombre simbólico; nada más.
            'alias' => ['required', 'string', 'regex:/^[a-z0-9]{3,30}$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'alias.regex' => 'El nombre tiene que tener entre 3 y 30 letras o números, sin espacios ni acentos.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['alias' => 'nombre del certificado'];
    }
}
