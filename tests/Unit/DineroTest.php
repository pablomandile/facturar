<?php

use App\Services\Arca\SerializadorDeFacturaC;
use App\Support\Dinero;

it('convierte a centavos los formatos que escribe la gente', function (string $texto, int $centavos) {
    expect(Dinero::aCentavos($texto))->toBe($centavos);
})->with([
    ['1500', 150_000],
    ['1500.5', 150_050],
    ['1500,50', 150_050],
    ['1.500,50', 150_050],
    ['$ 1.234.567,89', 123_456_789],
    ['0,01', 1],
]);

it('rechaza lo que no es un importe', function (string $texto) {
    expect(Dinero::esValido($texto))->toBeFalse();
})->with(['', 'diez', '10.555', '-5', '1,2,3']);

it('formatea y vuelve a decimal sin flotantes', function () {
    expect(Dinero::formatear(123_456_789))->toBe('1.234.567,89')
        ->and(Dinero::aDecimal(150_005))->toBe('1500.05')
        ->and(SerializadorDeFacturaC::importe(150_005))->toBe(1500.05)
        ->and(SerializadorDeFacturaC::importe(10))->toBe(0.1);
});

it('la suma de importes en pesos coincide con el total, sin error de redondeo', function () {
    // 0,1 + 0,2 con flotantes da 0,30000000000000004.
    expect(SerializadorDeFacturaC::importe(10 + 20))->toBe(SerializadorDeFacturaC::importe(30));
});
