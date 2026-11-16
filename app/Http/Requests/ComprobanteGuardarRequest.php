<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Arca\Concepto;
use App\Models\Comprobante;
use App\Models\User;
use App\Services\Arca\RelojFiscal;
use App\Support\Dinero;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de un borrador. Valida lo que ARCA va a rechazar seguro
 * (fechas fuera de rango, servicio sin período, consumidor final sobre el
 * tope) para que el error aparezca junto al campo y no como un rechazo.
 */
class ComprobanteGuardarRequest extends FormRequest
{
    public function authorize(): bool
    {
        $comprobante = $this->route('comprobante');

        if ($comprobante instanceof Comprobante) {
            return $this->user()?->can('update', $comprobante) ?? false;
        }

        return $this->usuario()->emisor !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['cliente_id', 'fecha_servicio_desde', 'fecha_servicio_hasta', 'fecha_vto_pago', 'observaciones'] as $campo) {
            if ($this->input($campo) === '') {
                $this->merge([$campo => null]);
            }
        }

        // Las filas vacías que deja el formulario no son ítems.
        $items = array_values(array_filter(
            (array) $this->input('items', []),
            fn (mixed $item): bool => is_array($item) && trim((string) ($item['descripcion'] ?? '')) !== '',
        ));
        $this->merge(['items' => $items]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $emisor = $this->usuario()->emisor;
        $servicios = fn (): bool => Concepto::tryFrom($this->integer('concepto'))?->exigeFechasDeServicio() ?? false;

        return [
            'punto_de_venta_id' => ['required', 'integer', Rule::exists('puntos_de_venta', 'id')
                ->where('emisor_id', $emisor?->id)
                ->where('ambiente', $emisor?->ambiente->value)
                ->where('activo', true)],
            'cliente_id' => ['nullable', 'integer', Rule::exists('clientes', 'id')
                ->where('emisor_id', $emisor?->id)
                ->whereNull('deleted_at')],
            'concepto' => ['required', Rule::enum(Concepto::class)],
            'fecha_emision' => ['required', 'date_format:Y-m-d'],
            'fecha_servicio_desde' => [Rule::requiredIf($servicios), 'nullable', 'date_format:Y-m-d'],
            'fecha_servicio_hasta' => [Rule::requiredIf($servicios), 'nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_servicio_desde'],
            'fecha_vto_pago' => [Rule::requiredIf($servicios), 'nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_emision'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.descripcion' => ['required', 'string', 'max:250'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:99999999', 'decimal:0,4'],
            'items.*.unidad' => ['nullable', 'string', 'max:20'],
            'items.*.precio_unitario' => ['required', 'string', 'max:20'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('items', []) as $i => $item) {
                    if (! Dinero::esValido((string) ($item['precio_unitario'] ?? ''))) {
                        $validator->errors()->add("items.{$i}.precio_unitario", 'El precio tiene que ser un número con hasta dos decimales.');
                    }
                }

                if ($validator->errors()->has('fecha_emision') || $validator->errors()->has('concepto')) {
                    return;
                }

                $concepto = Concepto::from($this->integer('concepto'));
                $hoy = (new RelojFiscal)->hoy();
                $fecha = $hoy->createFromFormat('!Y-m-d', (string) $this->input('fecha_emision'), 'UTC');

                if ($fecha !== null && abs($hoy->diffInDays($fecha, false)) > $concepto->ventanaDias()) {
                    $validator->errors()->add('fecha_emision', "Para {$concepto->etiqueta()} ARCA acepta hasta {$concepto->ventanaDias()} días antes o después de hoy.");
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required' => 'Agregá al menos un ítem.',
            'items.min' => 'Agregá al menos un ítem.',
            'punto_de_venta_id.exists' => 'Elegí un punto de venta activo.',
            'fecha_servicio_hasta.after_or_equal' => 'El período no puede terminar antes de empezar.',
            'fecha_vto_pago.after_or_equal' => 'El vencimiento del pago no puede ser anterior a la factura.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'punto_de_venta_id' => 'punto de venta',
            'cliente_id' => 'cliente',
            'fecha_emision' => 'fecha',
            'fecha_servicio_desde' => 'inicio del período',
            'fecha_servicio_hasta' => 'fin del período',
            'fecha_vto_pago' => 'vencimiento del pago',
            'items.*.descripcion' => 'descripción',
            'items.*.cantidad' => 'cantidad',
            'items.*.precio_unitario' => 'precio',
        ];
    }

    /**
     * Lo validado, con los tipos que espera ArmadorDeComprobantes.
     *
     * @return array{punto_de_venta_id: int, cliente_id: int|null, concepto: int, fecha_emision: string, fecha_servicio_desde: string|null, fecha_servicio_hasta: string|null, fecha_vto_pago: string|null, observaciones: string|null, items: list<array{descripcion: string, cantidad: string, unidad: string|null, precio_unitario: string}>}
     */
    public function datos(): array
    {
        $validado = $this->validated();
        $texto = fn (string $campo): ?string => isset($validado[$campo]) ? (string) $validado[$campo] : null;

        return [
            'punto_de_venta_id' => (int) $validado['punto_de_venta_id'],
            'cliente_id' => isset($validado['cliente_id']) ? (int) $validado['cliente_id'] : null,
            'concepto' => (int) $validado['concepto'],
            'fecha_emision' => (string) $validado['fecha_emision'],
            'fecha_servicio_desde' => $texto('fecha_servicio_desde'),
            'fecha_servicio_hasta' => $texto('fecha_servicio_hasta'),
            'fecha_vto_pago' => $texto('fecha_vto_pago'),
            'observaciones' => $texto('observaciones'),
            'items' => array_values(array_map(fn (array $item): array => [
                'descripcion' => (string) $item['descripcion'],
                'cantidad' => (string) $item['cantidad'],
                'unidad' => isset($item['unidad']) ? (string) $item['unidad'] : null,
                'precio_unitario' => (string) $item['precio_unitario'],
            ], (array) $validado['items'])),
        ];
    }

    private function usuario(): User
    {
        $usuario = $this->user();
        assert($usuario instanceof User);

        return $usuario;
    }
}
