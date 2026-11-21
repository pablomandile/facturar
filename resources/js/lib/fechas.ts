/**
 * "2026-11-16" → "16/11/2026". Las fechas de calendario llegan del servidor
 * como `YYYY-MM-DD` y se muestran tal cual, sin pasar por `Date`: un
 * `new Date('2026-11-16')` es medianoche UTC y en Argentina se ve como el 15.
 */
export function fechaCorta(iso: string | null | undefined): string {
    if (!iso) {
        return '';
    }

    const [anio, mes, dia] = iso.slice(0, 10).split('-');

    return `${dia}/${mes}/${anio}`;
}

const FECHA_Y_HORA = new Intl.DateTimeFormat('es-AR', {
    dateStyle: 'short',
    timeStyle: 'short',
    timeZone: 'America/Argentina/Buenos_Aires',
});

/**
 * Un instante (ISO 8601 con zona, guardado en UTC) en hora de Buenos Aires:
 * "21/11/26, 14:32". Para cuándo pasó algo, no para fechas de calendario.
 */
export function fechaYHora(iso: string | null | undefined): string {
    return iso ? FECHA_Y_HORA.format(new Date(iso)) : '';
}
