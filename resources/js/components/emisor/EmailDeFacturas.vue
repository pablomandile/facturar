<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref, useTemplateRef } from 'vue';
import EmisorController from '@/actions/App/Http/Controllers/EmisorController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import type { PlantillaEmail } from '@/types/emisor';

/*
 * El texto del email con que salen las facturas, con marcadores que el
 * servidor completa con los datos de cada una (TextoDelEmail). Vacío, sale el
 * texto por defecto.
 */
const props = defineProps<{ email: PlantillaEmail }>();

const plantilla = ref(props.email.plantilla ?? '');
const campo = useTemplateRef<HTMLTextAreaElement>('campo');

/** Inserta el marcador donde está el cursor, y deja el cursor detrás. */
function agregarMarcador(marcador: string): void {
    const inicio = campo.value?.selectionStart ?? plantilla.value.length;
    const fin = campo.value?.selectionEnd ?? inicio;

    plantilla.value =
        plantilla.value.slice(0, inicio) +
        marcador +
        plantilla.value.slice(fin);

    requestAnimationFrame(() => {
        campo.value?.focus();
        campo.value?.setSelectionRange(
            inicio + marcador.length,
            inicio + marcador.length,
        );
    });
}
</script>

<template>
    <Form
        v-bind="EmisorController.plantillaEmail.form()"
        v-slot="{ errors, processing }"
        class="grid gap-3"
    >
        <div class="flex flex-wrap gap-1.5">
            <Button
                v-for="m in props.email.marcadores"
                :key="m.marcador"
                type="button"
                variant="outline"
                size="sm"
                class="font-mono text-xs"
                :title="`Por ejemplo: ${m.ejemplo}`"
                @click="agregarMarcador(m.marcador)"
            >
                {{ m.marcador }}
            </Button>
        </div>
        <label for="plantilla_email" class="sr-only">Texto del email</label>
        <textarea
            id="plantilla_email"
            ref="campo"
            v-model="plantilla"
            name="plantilla_email"
            rows="8"
            :maxlength="props.email.maximo"
            :placeholder="props.email.por_defecto"
            class="field-sizing-content min-h-[calc(6lh+1rem)] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm leading-normal shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
            data-test="plantilla-email"
        />
        <p class="text-xs text-pretty text-muted-foreground">
            Vacío, sale el texto que se ve de fondo. Antes de cada envío lo
            podés retocar.
        </p>
        <InputError :message="errors.plantilla_email" />
        <div class="flex flex-wrap gap-2">
            <Button :disabled="processing" data-test="guardar-plantilla-email">
                Guardar el texto
            </Button>
            <Button
                v-if="plantilla === ''"
                type="button"
                variant="outline"
                @click="plantilla = props.email.por_defecto"
            >
                Partir del texto por defecto
            </Button>
        </div>
    </Form>
</template>
