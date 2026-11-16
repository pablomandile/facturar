<?php

use App\Enums\Arca\CondicionIvaReceptor;
use App\Enums\Arca\TipoDocumento;
use App\Models\Cliente;
use App\Models\Emisor;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function datosCliente(array $cambios = []): array
{
    return [
        'tipo_documento' => 80,
        'numero_documento' => '30-50001091-2',
        'nombre' => 'Panadería La Espiga SRL',
        'condicion_iva' => 1,
        'domicilio' => 'Corrientes 1234',
        'email' => 'compras@laespiga.test',
        ...$cambios,
    ];
}

beforeEach(function () {
    $this->emisor = Emisor::factory()->create();
    $this->actingAs($this->emisor->usuario);
});

it('sin datos fiscales manda a completarlos', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('clientes.index'))
        ->assertRedirect(route('emisor.edit'));
});

it('lista solo los clientes del emisor, por nombre', function () {
    Cliente::factory()->for($this->emisor)->create(['nombre' => 'Zapatería']);
    Cliente::factory()->for($this->emisor)->create(['nombre' => 'Almacén']);
    Cliente::factory()->create(['nombre' => 'De otra cuenta']);

    $this->get(route('clientes.index'))
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('clientes/Index')
            ->has('clientes.data', 2)
            ->where('clientes.data.0.nombre', 'Almacén')
            ->where('clientes.data.1.nombre', 'Zapatería'));
});

it('busca por nombre o por documento con o sin guiones', function () {
    Cliente::factory()->for($this->emisor)->create(['nombre' => 'Almacén', 'numero_documento' => '30500010912']);
    Cliente::factory()->for($this->emisor)->create(['nombre' => 'Zapatería']);

    $this->get(route('clientes.index', ['q' => 'zapa']))
        ->assertInertia(fn (Assert $p) => $p->has('clientes.data', 1)->where('clientes.data.0.nombre', 'Zapatería'));

    $this->get(route('clientes.index', ['q' => '30-50001091']))
        ->assertInertia(fn (Assert $p) => $p->has('clientes.data', 1)->where('clientes.data.0.nombre', 'Almacén'));
});

it('agrega un cliente con el documento normalizado', function () {
    $this->post(route('clientes.store'), datosCliente())->assertSessionHasNoErrors();

    expect(Cliente::query()->sole())
        ->emisor_id->toBe($this->emisor->id)
        ->numero_documento->toBe('30500010912')
        ->tipo_documento->toBe(TipoDocumento::Cuit)
        ->condicion_iva->toBe(CondicionIvaReceptor::ResponsableInscripto);
});

it('valida documento y condición frente al IVA', function (array $cambios, string $campo) {
    Cliente::factory()->for($this->emisor)->create(['numero_documento' => '20111111112']);

    $this->post(route('clientes.store'), datosCliente($cambios))->assertSessionHasErrors($campo);
})->with([
    'CUIT con verificador mal' => [['numero_documento' => '30-50001091-1'], 'numero_documento'],
    'DNI corto' => [['tipo_documento' => 96, 'numero_documento' => '123456', 'condicion_iva' => 5], 'numero_documento'],
    'inscripto con DNI' => [['tipo_documento' => 96, 'numero_documento' => '30123456', 'condicion_iva' => 1], 'condicion_iva'],
    'monotributista con CUIL' => [['tipo_documento' => 86, 'numero_documento' => '20-11111111-2', 'condicion_iva' => 6], 'condicion_iva'],
    'consumidor final no es un documento' => [['tipo_documento' => 99, 'numero_documento' => '0'], 'tipo_documento'],
    'documento repetido' => [['numero_documento' => '20-11111111-2'], 'numero_documento'],
    'email inválido' => [['email' => 'no-es-un-mail'], 'email'],
]);

it('acepta un consumidor final con DNI', function () {
    $this->post(route('clientes.store'), datosCliente(['tipo_documento' => 96, 'numero_documento' => '30.123.456', 'condicion_iva' => 5]))
        ->assertSessionHasNoErrors();

    expect(Cliente::query()->sole()->numero_documento)->toBe('30123456');
});

it('volver a cargar un cliente borrado lo restaura con los datos nuevos', function () {
    $borrado = Cliente::factory()->for($this->emisor)->create(['numero_documento' => '30500010912', 'nombre' => 'Viejo']);
    $borrado->delete();

    $this->post(route('clientes.store'), datosCliente(['nombre' => 'Nuevo']))->assertSessionHasNoErrors();

    expect(Cliente::query()->sole())->id->toBe($borrado->id)->nombre->toBe('Nuevo');
});

it('edita y borra', function () {
    $cliente = Cliente::factory()->for($this->emisor)->create();

    $this->put(route('clientes.update', $cliente), datosCliente(['nombre' => 'Editado']))->assertSessionHasNoErrors();
    expect($cliente->fresh()?->nombre)->toBe('Editado');

    $this->delete(route('clientes.destroy', $cliente))->assertRedirect();
    expect(Cliente::query()->count())->toBe(0)
        ->and(Cliente::withTrashed()->count())->toBe(1);
});

it('al editar no choca con su propio documento', function () {
    $cliente = Cliente::factory()->for($this->emisor)->create(['numero_documento' => '30500010912']);

    $this->put(route('clientes.update', $cliente), datosCliente())->assertSessionHasNoErrors();
});

it('no deja tocar los clientes de otra cuenta', function () {
    $ajeno = Cliente::factory()->create();

    $this->put(route('clientes.update', $ajeno), datosCliente())->assertForbidden();
    $this->delete(route('clientes.destroy', $ajeno))->assertForbidden();
});
