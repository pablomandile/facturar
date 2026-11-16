<?php

use App\Support\Arca\Cuit;

it('acepta CUIT con y sin guiones cuando el verificador es correcto', function (string $cuit) {
    expect(Cuit::esValido($cuit))->toBeTrue();
})->with(['20-11111111-2', '20111111112', '30-50001091-2', '33-69345023-9']);

it('rechaza verificador incorrecto, largo incorrecto y basura', function (string $cuit) {
    expect(Cuit::esValido($cuit))->toBeFalse();
})->with(['20-11111111-1', '2011111111', '201111111123', 'veinte', '']);

it('completa el verificador y descarta los prefijos sin CUIT posible', function () {
    expect(Cuit::conVerificador('2011111111'))->toBe('20111111112');

    // Con estos diez dígitos el verificador daría 10: no hay CUIT posible.
    $sinCuit = collect(range(0, 999))
        ->map(fn (int $n) => '20'.str_pad((string) $n, 8, '0', STR_PAD_LEFT))
        ->first(fn (string $diez) => Cuit::conVerificador($diez) === null);

    expect($sinCuit)->not->toBeNull();
});

it('formatea con guiones', function () {
    expect(Cuit::formatear('20111111112'))->toBe('20-11111111-2')
        ->and(Cuit::normalizar('20-11111111-2'))->toBe('20111111112');
});
