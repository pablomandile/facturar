<?php

namespace Database\Factories;

use App\Enums\Arca\Ambiente;
use App\Models\Emisor;
use App\Models\PuntoDeVenta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * El ambiente por defecto es homologación: el de un emisor recién creado.
 *
 * @extends Factory<PuntoDeVenta>
 */
class PuntoDeVentaFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'emisor_id' => Emisor::factory(),
            'ambiente' => Ambiente::Homologacion,
            'numero' => fake()->unique()->numberBetween(1, 9999),
            'descripcion' => null,
            'activo' => true,
        ];
    }
}
