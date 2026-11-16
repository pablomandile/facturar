/**
 * Plata en el front. Igual que en el servidor (App\Support\Dinero): todo en
 * centavos enteros, para que el total que se ve sea el mismo que se manda.
 */

const formato = new Intl.NumberFormat('es-AR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

/** 150050 → "1.500,50" */
export function formatearCentavos(centavos: number): string {
    return formato.format(centavos / 100);
}

/**
 * "1500", "1500.5", "1500,50" o "1.500,50" → centavos. Null si no es un
 * importe válido (más de dos decimales, letras, vacío).
 */
export function aCentavos(texto: string): number | null {
    let valor = texto.replace(/[\s$]/g, '');

    if (valor.includes(',')) {
        valor = valor.replace(/\./g, '').replace(',', '.');
    }

    const partes = /^(\d+)(?:\.(\d{1,2}))?$/.exec(valor);

    if (!partes) {
        return null;
    }

    return Number(partes[1]) * 100 + Number((partes[2] ?? '0').padEnd(2, '0'));
}

/** Cantidad × precio, redondeado al centavo, como en el servidor. */
export function importeDeItem(cantidad: string, precio: string): number {
    const centavos = aCentavos(precio);
    const unidades = Number(cantidad.replace(',', '.'));

    if (centavos === null || !Number.isFinite(unidades)) {
        return 0;
    }

    return Math.round(unidades * centavos);
}
