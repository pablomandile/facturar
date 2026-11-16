<?php

namespace Database\Factories;

use App\Enums\Arca\CondicionIvaReceptor;
use App\Enums\Arca\TipoDocumento;
use App\Models\Cliente;
use App\Models\Emisor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Por defecto, un responsable inscripto con CUIT.
 *
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'emisor_id' => Emisor::factory(),
            'tipo_documento' => TipoDocumento::Cuit,
            'numero_documento' => EmisorFactory::cuitValido(),
            'nombre' => fake()->company(),
            'condicion_iva' => CondicionIvaReceptor::ResponsableInscripto,
            'domicilio' => fake()->streetAddress(),
            'email' => fake()->safeEmail(),
        ];
    }

    public function conDni(): static
    {
        return $this->state([
            'tipo_documento' => TipoDocumento::Dni,
            'numero_documento' => (string) fake()->unique()->numberBetween(10_000_000, 45_000_000),
            'nombre' => fake()->name(),
            'condicion_iva' => CondicionIvaReceptor::ConsumidorFinal,
        ]);
    }
}
