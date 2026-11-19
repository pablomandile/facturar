/*
 * El importe en letras, como se escribe en una factura:
 *
 *   216916900 → "Dos millones ciento sesenta y nueve mil ciento sesenta y
 *                nueve pesos con cero centavos"
 *
 * Recibe centavos (enteros), igual que el resto de la app: nada de floats.
 *
 * Las reglas que se ven poco y se equivocan fácil:
 * - "uno" se apocopa delante de un sustantivo: "un peso", "veintiún mil",
 *   "treinta y un millones", "ciento un pesos". Pero 1.000 es "mil", no "un mil".
 * - Los millones exactos llevan "de": "un millón de pesos", pero "un millón
 *   doscientos mil pesos".
 * - 100 es "cien" solo; con algo detrás es "ciento".
 */

const UNIDADES = [
    'cero',
    'uno',
    'dos',
    'tres',
    'cuatro',
    'cinco',
    'seis',
    'siete',
    'ocho',
    'nueve',
    'diez',
    'once',
    'doce',
    'trece',
    'catorce',
    'quince',
    'dieciséis',
    'diecisiete',
    'dieciocho',
    'diecinueve',
    'veinte',
    'veintiuno',
    'veintidós',
    'veintitrés',
    'veinticuatro',
    'veinticinco',
    'veintiséis',
    'veintisiete',
    'veintiocho',
    'veintinueve',
];

const DECENAS = [
    '',
    '',
    '',
    'treinta',
    'cuarenta',
    'cincuenta',
    'sesenta',
    'setenta',
    'ochenta',
    'noventa',
];

const CENTENAS = [
    '',
    'ciento',
    'doscientos',
    'trescientos',
    'cuatrocientos',
    'quinientos',
    'seiscientos',
    'setecientos',
    'ochocientos',
    'novecientos',
];

/** "uno" → "un" al final, delante de un sustantivo: veintiuno → veintiún. */
function apocopar(texto: string): string {
    if (texto.endsWith('veintiuno')) {
        return texto.slice(0, -'veintiuno'.length) + 'veintiún';
    }

    if (texto === 'uno' || texto.endsWith(' uno')) {
        return texto.slice(0, -1);
    }

    return texto;
}

/** De 0 a 999. */
function hastaMil(n: number): string {
    if (n < 30) {
        return UNIDADES[n];
    }

    if (n < 100) {
        const unidad = n % 10;

        return (
            DECENAS[Math.floor(n / 10)] +
            (unidad ? ` y ${UNIDADES[unidad]}` : '')
        );
    }

    if (n === 100) {
        return 'cien';
    }

    const resto = n % 100;

    return CENTENAS[Math.floor(n / 100)] + (resto ? ` ${hastaMil(resto)}` : '');
}

/** De 0 a 999.999, apocopado si va delante de un sustantivo. */
function hastaUnMillon(n: number): string {
    const miles = Math.floor(n / 1000);
    const resto = n % 1000;
    const partes: string[] = [];

    if (miles === 1) {
        partes.push('mil');
    } else if (miles > 1) {
        partes.push(`${apocopar(hastaMil(miles))} mil`);
    }

    if (resto > 0 || miles === 0) {
        partes.push(hastaMil(resto));
    }

    return partes.join(' ');
}

/** Un entero no negativo en palabras, sin apocopar (como se lee el número solo). */
export function enteroEnLetras(n: number): string {
    if (!Number.isSafeInteger(n) || n < 0) {
        throw new RangeError(`No se puede escribir en letras: ${n}`);
    }

    const millones = Math.floor(n / 1_000_000);
    const resto = n % 1_000_000;
    const partes: string[] = [];

    if (millones === 1) {
        partes.push('un millón');
    } else if (millones > 1) {
        partes.push(`${apocopar(hastaUnMillon(millones))} millones`);
    }

    if (resto > 0 || millones === 0) {
        partes.push(hastaUnMillon(resto));
    }

    return partes.join(' ');
}

/**
 * Centavos → "Dos millones … pesos con cero centavos". Empieza con mayúscula:
 * en una descripción va al principio de una oración.
 */
export function importeEnLetras(centavos: number): string {
    if (!Number.isSafeInteger(centavos) || centavos < 0) {
        throw new RangeError(`Importe inválido: ${centavos}`);
    }

    const pesos = Math.floor(centavos / 100);
    const resto = centavos % 100;

    const textoPesos =
        pesos === 1
            ? 'un peso'
            : `${apocopar(enteroEnLetras(pesos))}${pesos >= 1_000_000 && pesos % 1_000_000 === 0 ? ' de' : ''} pesos`;

    const textoCentavos =
        resto === 1
            ? 'un centavo'
            : `${apocopar(enteroEnLetras(resto))} centavos`;

    const frase = `${textoPesos} con ${textoCentavos}`;

    return frase.charAt(0).toUpperCase() + frase.slice(1);
}
