import { describe, expect, it } from 'vite-plus/test';
import { enteroEnLetras, importeEnLetras } from './importeEnLetras';

describe('importeEnLetras', () => {
    it('escribe el ejemplo real de una factura', () => {
        expect(importeEnLetras(216_916_900)).toBe(
            'Dos millones ciento sesenta y nueve mil ciento sesenta y nueve pesos con cero centavos',
        );
    });

    it.each([
        [0, 'Cero pesos con cero centavos'],
        [100, 'Un peso con cero centavos'],
        [101, 'Un peso con un centavo'],
        [150, 'Un peso con cincuenta centavos'],
        [2_100, 'Veintiún pesos con cero centavos'],
        [3_199, 'Treinta y un pesos con noventa y nueve centavos'],
        [10_000, 'Cien pesos con cero centavos'],
        [10_100, 'Ciento un pesos con cero centavos'],
        [100_000, 'Mil pesos con cero centavos'],
        [2_100_000, 'Veintiún mil pesos con cero centavos'],
        [10_100_000, 'Ciento un mil pesos con cero centavos'],
        [100_000_000, 'Un millón de pesos con cero centavos'],
        [120_000_000, 'Un millón doscientos mil pesos con cero centavos'],
        [200_000_000, 'Dos millones de pesos con cero centavos'],
        [2_100_000_000, 'Veintiún millones de pesos con cero centavos'],
        [100_000_000_000, 'Mil millones de pesos con cero centavos'],
        [
            1_550_000_000_000,
            'Quince mil quinientos millones de pesos con cero centavos',
        ],
        [77_721, 'Setecientos setenta y siete pesos con veintiún centavos'],
    ])('%i centavos → %s', (centavos, esperado) => {
        expect(importeEnLetras(centavos)).toBe(esperado);
    });

    it('lee el número solo sin apocopar', () => {
        expect(enteroEnLetras(21)).toBe('veintiuno');
        expect(enteroEnLetras(31)).toBe('treinta y uno');
        expect(enteroEnLetras(1_001)).toBe('mil uno');
        expect(enteroEnLetras(16_022)).toBe('dieciséis mil veintidós');
    });

    it('rechaza lo que no es un importe en centavos', () => {
        expect(() => importeEnLetras(-1)).toThrow(RangeError);
        expect(() => importeEnLetras(1.5)).toThrow(RangeError);
    });
});
