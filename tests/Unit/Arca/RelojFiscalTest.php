<?php

use App\Services\Arca\RelojFiscal;
use Illuminate\Support\Carbon;

it('a las 22 h de Buenos Aires sigue siendo hoy, aunque en UTC ya sea mañana', function () {
    // 16/11 22:30 en Buenos Aires = 17/11 01:30 UTC.
    Carbon::setTestNow(Carbon::parse('2026-11-17 01:30:00', 'UTC'));

    $reloj = new RelojFiscal;

    expect($reloj->ymd($reloj->hoy()))->toBe('20261116')
        ->and($reloj->hoy()->timezoneName)->toBe('UTC')
        ->and($reloj->hoy()->toDateString())->toBe('2026-11-16');
});

it('lee y escribe fechas YYYYMMDD sin correrlas de día', function () {
    $reloj = new RelojFiscal;
    $fecha = $reloj->desdeYmd('20261231');

    expect($fecha->toDateString())->toBe('2026-12-31')
        ->and($reloj->ymd($fecha))->toBe('20261231');
});

it('rechaza una fecha de ARCA con formato inesperado', function () {
    (new RelojFiscal)->desdeYmd('2026-12-31');
})->throws(InvalidArgumentException::class);

it('el instante fiscal lleva la zona de Buenos Aires', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-17 01:30:00', 'UTC'));

    expect((new RelojFiscal)->ahora()->format('c'))->toBe('2026-11-16T22:30:00-03:00');
});
