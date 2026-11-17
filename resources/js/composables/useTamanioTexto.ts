import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import TamanioTextoController from '@/actions/App/Http/Controllers/Settings/TamanioTextoController';

export type TamanioTexto = 'pequeno' | 'normal' | 'grande';

export type OpcionTamanio = {
    valor: TamanioTexto;
    etiqueta: string;
    descripcion: string;
    /** El porcentaje como número (87.5, 100, 112.5), para calcular la muestra. */
    escala: number;
};

export type UseTamanioTextoReturn = {
    tamanio: ComputedRef<TamanioTexto>;
    opciones: ComputedRef<OpcionTamanio[]>;
    cambiarTamanio: (valor: TamanioTexto) => void;
};

/**
 * El servidor escribe `data-texto` en el `<html>` de arranque. Esto es para
 * cuando se elige desde Configuración: Inertia no recarga el documento, así
 * que el atributo lo mueve el cliente o no se vería hasta una carga completa.
 */
function aplicarAlDocumento(valor: TamanioTexto): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.dataset.texto = valor;
}

export function useTamanioTexto(): UseTamanioTextoReturn {
    const page = usePage();

    const tamanio = computed<TamanioTexto>(
        () => (page.props.tamanioTexto as TamanioTexto | undefined) ?? 'normal',
    );

    const opciones = computed<OpcionTamanio[]>(
        () => (page.props.tamaniosTexto as OpcionTamanio[] | undefined) ?? [],
    );

    function cambiarTamanio(valor: TamanioTexto): void {
        if (valor === tamanio.value) {
            return;
        }

        // Se aplica antes de que conteste el servidor; si falla, se revierte.
        const anterior = tamanio.value;

        aplicarAlDocumento(valor);

        router.put(
            TamanioTextoController.update.url(),
            { tamanio_texto: valor },
            {
                preserveScroll: true,
                // Para que `tamanioTexto` se relea de las props: con el estado
                // preservado el selector seguiría marcando la opción vieja.
                preserveState: false,
                onError: () => aplicarAlDocumento(anterior),
            },
        );
    }

    return { tamanio, opciones, cambiarTamanio };
}
