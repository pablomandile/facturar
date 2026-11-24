<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Services\Facturas\TextoDelEmail;
use Illuminate\Foundation\Http\FormRequest;

/**
 * El texto con marcadores del email de las facturas. Vacío vuelve al texto
 * por defecto (TextoDelEmail::POR_DEFECTO).
 */
class PlantillaEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        return $usuario instanceof User && $usuario->emisor !== null && $usuario->can('update', $usuario->emisor);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('asunto_email'))) {
            $this->merge(['asunto_email' => TextoDelEmail::unaLinea((string) $this->input('asunto_email'))]);
        }

        if (is_string($this->input('plantilla_email'))) {
            $this->merge(['plantilla_email' => trim(str_replace("\r\n", "\n", (string) $this->input('plantilla_email')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'asunto_email' => ['nullable', 'string', 'max:'.TextoDelEmail::ASUNTO_MAXIMO],
            'plantilla_email' => ['nullable', 'string', 'max:'.TextoDelEmail::PLANTILLA_MAXIMA],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['plantilla_email' => 'texto del email', 'asunto_email' => 'asunto'];
    }

    public function asunto(): ?string
    {
        $asunto = (string) $this->validated('asunto_email');

        return $asunto === '' ? null : $asunto;
    }

    public function plantilla(): ?string
    {
        $plantilla = (string) $this->validated('plantilla_email');

        return $plantilla === '' ? null : $plantilla;
    }
}
