import { importeEnLetras } from '@/lib/importeEnLetras';
import { formatearCentavos } from '@/lib/moneda';

/*
 * La "descripción habitual" de un cliente: un texto con marcadores que se
 * completan con los datos de cada factura.
 *
 *   Facturación del período {desde} al {hasta}.
 *   {importe_en_letras}.
 *
 * Un marcador desconocido queda tal cual, a la vista: así se nota el error de
 * tipeo en vez de desaparecer en silencio.
 */

export type DatosDeLaFactura = {
    /** Período facturado, YYYY-MM-DD. Vacío si la factura no es de servicios. */
    desde: string;
    hasta: string;
    /** Total de la factura, en centavos. */
    totalCentavos: number;
};

export const MARCADORES = [
    { marcador: '{desde}', ejemplo: '01/10/2026' },
    { marcador: '{hasta}', ejemplo: '31/10/2026' },
    { marcador: '{mes}', ejemplo: 'octubre' },
    { marcador: '{anio}', ejemplo: '2026' },
    { marcador: '{importe}', ejemplo: '$ 2.169.169,00' },
    {
        marcador: '{importe_en_letras}',
        ejemplo: 'Dos millones ciento sesenta y nueve mil…',
    },
] as const;

const MESES = [
    'enero',
    'febrero',
    'marzo',
    'abril',
    'mayo',
    'junio',
    'julio',
    'agosto',
    'septiembre',
    'octubre',
    'noviembre',
    'diciembre',
];

/** YYYY-MM-DD → DD/MM/YYYY, sin pasar por Date (que lo correría de zona). */
function fecha(iso: string): string {
    const [anio, mes, dia] = iso.split('-');

    return anio && mes && dia ? `${dia}/${mes}/${anio}` : '';
}

export function completarPlantilla(
    plantilla: string,
    datos: DatosDeLaFactura,
): string {
    const [anio = '', mes = ''] = datos.desde.split('-');

    const valores: Record<string, string> = {
        desde: fecha(datos.desde),
        hasta: fecha(datos.hasta),
        mes: MESES[Number(mes) - 1] ?? '',
        anio,
        importe: `$ ${formatearCentavos(datos.totalCentavos)}`,
        importe_en_letras: importeEnLetras(Math.max(0, datos.totalCentavos)),
    };

    return plantilla.replace(/\{([a-z_]+)\}/g, (original, nombre: string) =>
        nombre in valores ? valores[nombre] : original,
    );
}
