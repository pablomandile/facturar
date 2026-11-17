<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import { useTamanioTexto } from '@/composables/useTamanioTexto';
import type { TamanioTexto } from '@/composables/useTamanioTexto';

const { tamanio, opciones, cambiarTamanio } = useTamanioTexto();

const escalaActual = computed(
    () => opciones.value.find((o) => o.valor === tamanio.value)?.escala ?? 100,
);

/*
 * Cada muestra al tamaño que tendría la app con esa opción. En `em` contra el
 * tamaño vigente y no en px: en px mentiría para quien cambió el tamaño de su
 * navegador, que es justo quien viene a esta pantalla.
 */
function escalaDeLaMuestra(escala: number): string {
    return `${escala / escalaActual.value}em`;
}

function esElegido(valor: TamanioTexto): boolean {
    return valor === tamanio.value;
}
</script>

<template>
    <fieldset class="space-y-3">
        <legend class="sr-only">Tamaño de la letra</legend>

        <label
            v-for="opcion in opciones"
            :key="opcion.valor"
            :class="[
                'flex cursor-pointer gap-3 rounded-lg border p-4 transition-colors',
                'has-focus-visible:ring-2 has-focus-visible:ring-ring has-focus-visible:ring-offset-2',
                esElegido(opcion.valor)
                    ? 'border-primary bg-muted'
                    : 'border-border hover:bg-muted/50',
            ]"
            :data-test="`tamanio-${opcion.valor}`"
        >
            <!-- Un radio de verdad: flechas, barra y lectura del estado gratis. -->
            <input
                type="radio"
                name="tamanio_texto"
                class="sr-only"
                :value="opcion.valor"
                :checked="esElegido(opcion.valor)"
                @change="cambiarTamanio(opcion.valor)"
            />

            <div
                :class="[
                    'mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full border-2',
                    esElegido(opcion.valor)
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-muted-foreground/40',
                ]"
                aria-hidden="true"
            >
                <Check v-if="esElegido(opcion.valor)" class="size-4" />
            </div>

            <div class="min-w-0 flex-1 space-y-2">
                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                    <span class="font-medium">{{ opcion.etiqueta }}</span>
                    <span
                        v-if="esElegido(opcion.valor)"
                        class="text-xs text-muted-foreground"
                        >(el que estás usando)</span
                    >
                </div>

                <p class="text-sm text-muted-foreground">
                    {{ opcion.descripcion }}
                </p>

                <p
                    class="border-t border-border/60 pt-2 leading-snug wrap-break-word"
                    :style="{ fontSize: escalaDeLaMuestra(opcion.escala) }"
                >
                    Factura C 0001-00000123 · $&nbsp;15.000,00
                </p>
            </div>
        </label>
    </fieldset>
</template>
