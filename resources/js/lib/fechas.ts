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
