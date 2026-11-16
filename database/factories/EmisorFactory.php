<?php

namespace Database\Factories;

use App\Enums\Arca\Ambiente;
use App\Models\Emisor;
use App\Models\User;
use App\Support\Arca\Cuit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Emisor>
 */
class EmisorFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'usuario_id' => User::factory(),
            'cuit' => self::cuitValido(),
            'razon_social' => fake()->name(),
            'domicilio_fiscal' => fake()->streetAddress().', CABA',
            'ingresos_brutos' => null,
            'inicio_actividades' => fake()->dateTimeBetween('-10 years', '-1 month')->format('Y-m-d'),
            'ambiente' => Ambiente::Homologacion,
        ];
    }

    public function enProduccion(): static
    {
        return $this->state(['ambiente' => Ambiente::Produccion]);
    }

    /** Un CUIT de persona humana (20-) con el verificador correcto. */
    public static function cuitValido(): string
    {
        do {
            $cuit = Cuit::conVerificador('20'.fake()->unique()->numerify('########'));
        } while ($cuit === null);

        return $cuit;
    }
}
