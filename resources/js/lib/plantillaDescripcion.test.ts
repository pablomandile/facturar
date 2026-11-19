import { describe, expect, it } from 'vite-plus/test';
import { completarPlantilla } from './plantillaDescripcion';

const OCTUBRE = {
    desde: '2026-10-01',
    hasta: '2026-10-31',
    totalCentavos: 216_916_900,
};

describe('completarPlantilla', () => {
    it('arma la descripción real, con sus saltos de línea', () => {
        const plantilla =
            'Facturación correspondiente al período comprendido entre el {desde} al {hasta}, en la Cuenta Bco. Galicia N° 400201251943 - Sucursal N° 999.\n{importe_en_letras}.';

        expect(completarPlantilla(plantilla, OCTUBRE)).toBe(
            'Facturación correspondiente al período comprendido entre el 01/10/2026 al 31/10/2026, en la Cuenta Bco. Galicia N° 400201251943 - Sucursal N° 999.\nDos millones ciento sesenta y nueve mil ciento sesenta y nueve pesos con cero centavos.',
        );
    });

    it('completa el mes, el año y el importe en números', () => {
        expect(
            completarPlantilla(
                'Honorarios de {mes} {anio}: {importe}',
                OCTUBRE,
            ),
        ).toMatch(/^Honorarios de octubre 2026: \$\s2\.169\.169,00$/);
    });

    it('deja a la vista un marcador que no existe', () => {
        expect(completarPlantilla('Período {dsde}', OCTUBRE)).toBe(
            'Período {dsde}',
        );
    });

    it('sin período (productos) deja las fechas vacías y no rompe', () => {
        expect(
            completarPlantilla('del {desde} al {hasta} ({mes})', {
                desde: '',
                hasta: '',
                totalCentavos: 0,
            }),
        ).toBe('del  al  ()');
    });

    it('repite un marcador las veces que aparezca', () => {
        expect(completarPlantilla('{anio}/{anio}', OCTUBRE)).toBe('2026/2026');
    });
});
