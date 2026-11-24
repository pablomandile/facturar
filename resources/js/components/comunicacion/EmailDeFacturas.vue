<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref, useTemplateRef } from 'vue';
import ComunicacionController from '@/actions/App/Http/Controllers/ComunicacionController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import type { PlantillaEmail } from '@/types/comunicacion';

/*
 * El asunto y el texto del email con que salen las facturas, con marcadores
 * que el servidor completa con los datos de cada una (TextoDelEmail). Vacíos,
 * salen los de por defecto. Los botones de marcador escriben en el campo que
 * tuvo el cursor por última vez.
 */
const props = defineProps<{ email: PlantillaEmail }>();

const asunto = ref(props.email.asunto ?? '');
const plantilla = ref(props.email.plantilla ?? '');
const campoAsunto = useTemplateRef<HTMLInputElement>('campoAsunto');
const campoTexto = useTemplateRef<HTMLTextAreaElement>('campoTexto');
const activo = ref<'asunto' | 'texto'>('texto');

/** Inserta el marcador donde está el cursor, y deja el cursor detrás. */
function agregarMarcador(marcador: string): void {
    const enAsunto = activo.value === 'asunto';
    const campo = enAsunto ? campoAsunto.value : campoTexto.value;
    const valor = enAsunto ? asunto : plantilla;
    const inicio = campo?.selectionStart ?? valor.value.length;
    const fin = campo?.selectionEnd ?? inicio;

    valor.value =
        valor.value.slice(0, inicio) + marcador + valor.value.slice(fin);

    requestAnimationFrame(() => {
        campo?.focus();
        campo?.setSelectionRange(
            inicio + marcador.length,
            inicio + marcador.length,
        );
    });
}

const claseCampo =
    'w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <Form
        v-bind="ComunicacionController.update.form()"
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
                @mousedown.prevent
                @click="agregarMarcador(m.marcador)"
            >
                {{ m.marcador }}
            </Button>
        </div>

        <div class="grid gap-1.5">
            <label for="asunto_email" class="text-sm font-medium">Asunto</label>
            <input
                id="asunto_email"
                ref="campoAsunto"
                v-model="asunto"
                name="asunto_email"
                :maxlength="props.email.asunto_maximo"
                :placeholder="props.email.asunto_por_defecto"
                autocomplete="off"
                :class="[claseCampo, 'h-9']"
                data-test="asunto-email"
                @focus="activo = 'asunto'"
            />
            <InputError :message="errors.asunto_email" />
        </div>

        <div class="grid gap-1.5">
            <label for="plantilla_email" class="text-sm font-medium"
                >Texto</label
            >
            <textarea
                id="plantilla_email"
                ref="campoTexto"
                v-model="plantilla"
                name="plantilla_email"
                rows="8"
                :maxlength="props.email.maximo"
                :placeholder="props.email.por_defecto"
                :class="[
                    claseCampo,
                    'field-sizing-content min-h-[calc(6lh+1rem)] py-2 leading-normal',
                ]"
                data-test="plantilla-email"
                @focus="activo = 'texto'"
            />
            <InputError :message="errors.plantilla_email" />
        </div>

        <p class="text-xs text-pretty text-muted-foreground">
            Vacíos, salen los que se ven de fondo. Antes de cada envío los podés
            retocar.
        </p>
        <div class="flex flex-wrap gap-2">
            <Button :disabled="processing" data-test="guardar-plantilla-email">
                Guardar el email
            </Button>
            <Button
                v-if="asunto === '' || plantilla === ''"
                type="button"
                variant="outline"
                @click="
                    asunto ||= props.email.asunto_por_defecto;
                    plantilla ||= props.email.por_defecto;
                "
            >
                Partir de los de por defecto
            </Button>
        </div>
    </Form>
</template>
