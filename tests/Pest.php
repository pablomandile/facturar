<?php

use App\Contracts\Arca\FabricaDeTransportes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Arca\ArcaFalso;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Los tests unitarios también arrancan la app: los casts de Eloquent (fechas,
// enums, encrypted) y los servicios de ARCA resuelven config del container.
pest()->extend(TestCase::class)->in('Unit');

/**
 * Reemplaza el cable hacia ARCA por uno falso y lo devuelve para preparar
 * respuestas. Va **antes** de resolver cualquier servicio que hable con ARCA:
 * uno resuelto antes se queda con la fábrica real.
 */
function arcaFalso(): ArcaFalso
{
    $arca = new ArcaFalso;
    app()->instance(FabricaDeTransportes::class, $arca);

    return $arca;
}
