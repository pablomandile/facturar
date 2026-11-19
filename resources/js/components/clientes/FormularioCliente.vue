<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, useTemplateRef } from 'vue';
import ClienteController from '@/actions/App/Http/Controllers/ClienteController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { completarPlantilla, MARCADORES } from '@/lib/plantillaDescripcion';
import type {
    Cliente,
    OpcionCodigo,
    OpcionCondicionIva,
} from '@/types/clientes';

const props = defineProps<{
    cliente: Cliente | null;
    tiposDocumento: OpcionCodigo[];
    condicionesIva: OpcionCondicionIva[];
}>();

const emit = defineEmits<{ guardado: [] }>();

// Por defecto: CUIT y responsable inscripto, el caso más común al facturar
// a una empresa. Con DNI casi siempre es consumidor final.
const tipo = ref(props.cliente?.tipo_documento ?? 80);
const condicion = ref(props.cliente?.condicion_iva ?? 1);

const exigeCuit = computed(
    () =>
        props.condicionesIva.find((c) => c.value === condicion.value)
            ?.exige_cuit ?? false,
);

const plantilla = ref(props.cliente?.plantilla_descripcion ?? '');

// Cómo va a quedar, con un período y un total de ejemplo.
const vistaPrevia = computed(() =>
    plantilla.value.trim() === ''
        ? ''
        : completarPlantilla(plantilla.value, {
              desde: '2026-10-01',
              hasta: '2026-10-31',
              totalCentavos: 216_916_900,
          }),
);

const campoPlantilla = useTemplateRef<HTMLTextAreaElement>('campoPlantilla');

/** Inserta el marcador donde está el cursor, y deja el cursor detrás. */
function agregarMarcador(marcador: string): void {
    const campo = campoPlantilla.value;
    const inicio = campo?.selectionStart ?? plantilla.value.length;
    const fin = campo?.selectionEnd ?? inicio;

    plantilla.value =
        plantilla.value.slice(0, inicio) +
        marcador +
        plantilla.value.slice(fin);

    requestAnimationFrame(() => {
        campo?.focus();
        campo?.setSelectionRange(
            inicio + marcador.length,
            inicio + marcador.length,
        );
    });
}

const formulario = computed(() =>
    props.cliente
        ? ClienteController.update.form(props.cliente.id)
        : ClienteController.store.form(),
);

const claseSelect =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <Form
        v-bind="formulario"
        class="grid gap-4 sm:grid-cols-2"
        v-slot="{ errors, processing }"
        @success="emit('guardado')"
    >
        <div class="grid gap-2 sm:col-span-2">
            <Label for="nombre">Nombre o razón social</Label>
            <Input
                id="nombre"
                name="nombre"
                :default-value="cliente?.nombre"
                required
            />
            <InputError :message="errors.nombre" />
        </div>

        <div class="grid gap-2">
            <Label for="tipo_documento">Documento</Label>
            <select
                id="tipo_documento"
                v-model="tipo"
                name="tipo_documento"
                :class="claseSelect"
            >
                <option
                    v-for="opcion in tiposDocumento"
                    :key="opcion.value"
                    :value="opcion.value"
                >
                    {{ opcion.etiqueta }}
                </option>
            </select>
            <InputError :message="errors.tipo_documento" />
        </div>

        <div class="grid gap-2">
            <Label for="numero_documento">Número</Label>
            <Input
                id="numero_documento"
                name="numero_documento"
                inputmode="numeric"
                :default-value="cliente?.numero_documento"
                :placeholder="tipo === 96 ? '12345678' : '30-12345678-9'"
                required
            />
            <InputError :message="errors.numero_documento" />
        </div>

        <div class="grid gap-2 sm:col-span-2">
            <Label for="condicion_iva">Condición frente al IVA</Label>
            <select
                id="condicion_iva"
                v-model="condicion"
                name="condicion_iva"
                :class="claseSelect"
            >
                <option
                    v-for="opcion in condicionesIva"
                    :key="opcion.value"
                    :value="opcion.value"
                >
                    {{ opcion.etiqueta }}
                </option>
            </select>
            <p
                v-if="exigeCuit && tipo !== 80"
                class="text-xs text-muted-foreground"
            >
                Para esta condición ARCA pide identificarlo con CUIT.
            </p>
            <InputError :message="errors.condicion_iva" />
        </div>

        <div class="grid gap-2 sm:col-span-2">
            <Label for="domicilio">Domicilio (opcional)</Label>
            <Input
                id="domicilio"
                name="domicilio"
                :default-value="cliente?.domicilio ?? ''"
                autocomplete="off"
            />
            <InputError :message="errors.domicilio" />
        </div>

        <div class="grid gap-2 sm:col-span-2">
            <Label for="email">Email (opcional)</Label>
            <Input
                id="email"
                name="email"
                type="email"
                :default-value="cliente?.email ?? ''"
                autocomplete="off"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2 sm:col-span-2">
            <Label for="plantilla_descripcion"
                >Descripción habitual (opcional)</Label
            >
            <p class="text-xs text-pretty text-muted-foreground">
                Si a este cliente le facturás siempre lo mismo, escribila acá:
                al hacerle una factura se completa sola. Tocá un marcador para
                sumarlo; se reemplaza con los datos de cada factura.
            </p>
            <div class="flex flex-wrap gap-1.5">
                <Button
                    v-for="m in MARCADORES"
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
            <textarea
                id="plantilla_descripcion"
                ref="campoPlantilla"
                v-model="plantilla"
                name="plantilla_descripcion"
                rows="3"
                maxlength="1000"
                class="field-sizing-content min-h-[calc(3lh+1rem)] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm leading-normal shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                placeholder="Facturación del período {desde} al {hasta}.&#10;{importe_en_letras}."
            />
            <InputError :message="errors.plantilla_descripcion" />
            <div
                v-if="vistaPrevia"
                class="rounded-md border border-dashed p-3 text-sm"
                data-test="vista-previa-descripcion"
            >
                <p class="mb-1 text-xs text-muted-foreground">
                    Así queda con un período de octubre y un total de $
                    2.169.169,00:
                </p>
                <p class="wrap-break-word whitespace-pre-line">
                    {{ vistaPrevia }}
                </p>
            </div>
        </div>

        <div class="sm:col-span-2">
            <Button :disabled="processing" data-test="guardar-cliente">
                {{ cliente ? 'Guardar cambios' : 'Agregar cliente' }}
            </Button>
        </div>
    </Form>
</template>
