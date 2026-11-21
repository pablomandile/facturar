<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Arca\Ambiente;
use App\Models\Comprobante;
use App\Models\User;
use App\Services\Facturas\EnviadorDeFacturas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Mandar una factura por email desde el detalle: a quién (hasta tres
 * direcciones), si va con copia a la cuenta y el texto, ya completo y quizás
 * retocado a mano.
 */
class EnviarComprobanteRequest extends FormRequest
{
    public const TEXTO_MAXIMO = 5000;

    public function authorize(): bool
    {
        return $this->usuario()->can('enviar', $this->comprobante());
    }

    /**
     * "Para" llega como un solo campo con las direcciones separadas por coma,
     * punto y coma o espacios: se parte en una lista antes de validar.
     */
    protected function prepareForValidation(): void
    {
        $para = $this->input('para');

        if (is_string($para)) {
            $this->merge(['para' => array_values(array_filter(preg_split('/[\s,;]+/', $para) ?: []))]);
        }

        if (is_string($this->input('texto'))) {
            $this->merge(['texto' => trim(str_replace("\r\n", "\n", (string) $this->input('texto')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'para' => ['required', 'array', 'min:1', 'max:'.EnviadorDeFacturas::DESTINATARIOS_MAXIMOS],
            'para.*' => ['required', 'string', 'email:rfc', 'max:254'],
            'copia_a_mi' => ['nullable', 'boolean'],
            'texto' => ['required', 'string', 'max:'.self::TEXTO_MAXIMO],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $propio = mb_strtolower($this->usuario()->email);
                $para = array_map(fn (mixed $email): string => mb_strtolower(trim((string) $email)), (array) $this->input('para'));

                if ($this->comprobante()->ambiente === Ambiente::Homologacion && array_values(array_unique($para)) !== [$propio]) {
                    $validator->errors()->add('para', 'Una factura de homologación solo se puede enviar a tu propio email.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'para.required' => 'Indicá al menos una dirección.',
            'para.max' => 'Como máximo, :max direcciones.',
            'para.*.email' => '":input" no es un email válido.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['para' => 'destinatarios', 'texto' => 'mensaje'];
    }

    /** @return list<string> */
    public function destinatarios(): array
    {
        return array_values(array_map('strval', (array) $this->validated('para')));
    }

    public function comprobante(): Comprobante
    {
        $comprobante = $this->route('comprobante');
        assert($comprobante instanceof Comprobante);

        return $comprobante;
    }

    private function usuario(): User
    {
        $usuario = $this->user();
        assert($usuario instanceof User);

        return $usuario;
    }
}
