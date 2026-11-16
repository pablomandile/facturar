<?php

use App\Enums\EstadoComprobante;
use App\Models\Certificado;
use App\Models\Comprobante;
use App\Models\Emisor;
use App\Models\PuntoDeVenta;
use App\Models\User;
use App\Services\Arca\RelojFiscal;
use Inertia\Testing\AssertableInertia as Assert;

it('a una cuenta nueva le muestra lo que falta configurar', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $p) => $p
            ->component('Dashboard')
            ->where('configurado', false)
            ->where('pasos.perfil', false));
});

it('suma lo autorizado del mes y avisa lo que necesita atención', function () {
    $emisor = Emisor::factory()->create();
    Certificado::factory()->vigente()->for($emisor)->create(['valido_hasta' => now()->addDays(20)]);
    $punto = PuntoDeVenta::factory()->for($emisor)->create();
    $hoy = (new RelojFiscal)->hoy()->toDateString();

    Comprobante::factory()->paraPunto($punto)->autorizado(1)->conTotal(150_000)->create(['fecha_emision' => $hoy]);
    Comprobante::factory()->paraPunto($punto)->autorizado(2)->conTotal(50_050)->create(['fecha_emision' => $hoy]);
    Comprobante::factory()->paraPunto($punto)->autorizado(3)->conTotal(999_999)->create(['fecha_emision' => (new RelojFiscal)->hoy()->subMonths(2)->toDateString()]);
    Comprobante::factory()->paraPunto($punto)->create(['estado' => EstadoComprobante::Rechazado]);

    $this->actingAs($emisor->usuario)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $p) => $p
            ->where('configurado', true)
            ->where('mes.total', '2.000,50')
            ->where('mes.cantidad', 2)
            ->where('certificado.dias', 19)
            ->where('atencion.con_problemas', 1)
            ->has('ultimas', 4));
});
