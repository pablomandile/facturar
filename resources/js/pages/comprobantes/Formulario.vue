<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Plus, RefreshCw, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ComprobanteController from '@/actions/App/Http/Controllers/ComprobanteController';
import EmisorController from '@/actions/App/Http/Controllers/EmisorController';
import InputError from '@/components/InputError.vue';
import Seccion from '@/components/Seccion.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatearCentavos, importeDeItem } from '@/lib/moneda';
import { completarPlantilla } from '@/lib/plantillaDescripcion';
import type {
    ComprobanteBase,
    ComprobanteEditable,
    ItemDelFormulario,
    Opcion,
    OpcionCliente,
    OpcionConcepto,
} from '@/types/comprobantes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Facturas', href: ComprobanteController.index() },
            { title: 'Factura C', href: ComprobanteController.create() },
        ],
    },
});

const props = defineProps<{
    comprobante: ComprobanteEditable | null;
    /** Una factura duplicada: precarga el formulario, pero se guarda como nueva. */
    base: ComprobanteBase | null;
    /** De qué sale la copia ("la factura 00001-…" o "un borrador"). */
    duplicadoDe: string | null;
    /** Solo en una factura nueva: el cliente con el que arranca. */
    clienteSugerido?: number | null;
    puntosDeVenta: Opcion[];
    clientes: OpcionCliente[];
    conceptos: OpcionConcepto[];
    hoy: string;
    inicioDeMes: string;
    finDeMes: string;
    topeConsumidorFinal: number;
    ambiente: string;
    esProduccion: boolean;
}>();

const inicial = props.comprobante ?? props.base;

const itemVacio = (): ItemDelFormulario => ({
    descripcion: '',
    cantidad: '1',
    unidad: null,
    precio_unitario: '',
});

const items = ref<ItemDelFormulario[]>(
    inicial?.items.length
        ? inicial.items.map((item) => ({ ...item }))
        : [itemVacio()],
);
const concepto = ref(inicial?.concepto ?? 2);
const clienteId = ref<string>(
    String(
        inicial ? (inicial.cliente_id ?? '') : (props.clienteSugerido ?? ''),
    ),
);
const desde = ref(inicial?.fecha_servicio_desde ?? props.inicioDeMes);
const hasta = ref(inicial?.fecha_servicio_hasta ?? props.finDeMes);
// Qué botón se tocó: el campo oculto viaja con el formulario.
const emitir = ref(false);

const conServicios = computed(
    () =>
        props.conceptos.find((c) => c.value === Number(concepto.value))
            ?.con_servicios ?? false,
);
const total = computed(() =>
    items.value.reduce(
        (suma, item) =>
            suma + importeDeItem(item.cantidad, item.precio_unitario),
        0,
    ),
);
const consumidorFinalSobreElTope = computed(
    () => clienteId.value === '' && total.value >= props.topeConsumidorFinal,
);

/*
 * La descripción habitual del cliente (ver lib/plantillaDescripcion.ts) va en
 * el primer ítem y se rehace sola mientras cambian el período o el total. En
 * cuanto la persona la edita a mano, deja de pisarla; el botón "Usar la
 * descripción habitual" la vuelve a encender.
 *
 * Al editar un borrador arranca apagada: lo guardado se respeta.
 */
const clienteElegido = computed(
    () => props.clientes.find((c) => String(c.id) === clienteId.value) ?? null,
);
const plantilla = computed(() => clienteElegido.value?.plantilla ?? null);

/*
 * "Enviarla a … cuando ARCA la autorice" (EnviarAlAutorizar): solo en
 * producción y con un cliente que tenga email. Tildada salvo que un borrador
 * guardado diga lo contrario.
 */
const emailDelCliente = computed(() =>
    props.esProduccion ? (clienteElegido.value?.email ?? null) : null,
);
const enviarAlAutorizar = ref(inicial?.enviar_al_autorizar ?? true);
const descripcionGenerada = computed(() =>
    plantilla.value
        ? completarPlantilla(plantilla.value, {
              desde: conServicios.value ? desde.value : '',
              hasta: conServicios.value ? hasta.value : '',
              totalCentavos: total.value,
          })
        : null,
);
const descripcionAutomatica = ref(props.comprobante === null);

watch(
    descripcionGenerada,
    (texto) => {
        if (descripcionAutomatica.value && texto !== null && items.value[0]) {
            items.value[0].descripcion = texto;
        }
    },
    { immediate: true },
);

watch(plantilla, (nueva, anterior) => {
    const primero = items.value[0];

    if (!primero) {
        return;
    }

    // Un cliente con descripción habitual y el campo vacío: se completa.
    if (nueva !== null && primero.descripcion.trim() === '') {
        usarDescripcionHabitual();

        return;
    }

    // Al pasar a un cliente sin descripción habitual, no queda la del anterior.
    if (descripcionAutomatica.value && nueva === null && anterior !== null) {
        primero.descripcion = '';
    }
});

function editarDescripcion(i: number): void {
    if (i === 0 && items.value[0].descripcion !== descripcionGenerada.value) {
        descripcionAutomatica.value = false;
    }
}

function usarDescripcionHabitual(): void {
    descripcionAutomatica.value = true;

    if (descripcionGenerada.value !== null) {
        items.value[0].descripcion = descripcionGenerada.value;
    }
}

const formulario = computed(() =>
    props.comprobante
        ? ComprobanteController.update.form(props.comprobante.id)
        : ComprobanteController.store.form(),
);

const claseCampo =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <Head :title="comprobante ? 'Editar factura' : 'Nueva factura'" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
        <header class="space-y-1">
            <h1 class="text-xl font-semibold tracking-tight">
                {{ comprobante ? 'Editar factura C' : 'Nueva factura C' }}
            </h1>
            <p class="text-sm text-muted-foreground">{{ ambiente }}</p>
            <p
                v-if="duplicadoDe"
                class="text-sm text-muted-foreground"
                data-test="aviso-duplicado"
            >
                Copia de {{ duplicadoDe }}, con el período corrido al mes que
                corresponde. Revisá el importe antes de emitir.
            </p>
        </header>

        <p v-if="!puntosDeVenta.length" class="rounded-lg border p-4 text-sm">
            Para facturar necesitás un punto de venta activo.
            <Link
                :href="EmisorController.edit()"
                class="underline underline-offset-4"
                >Cargalo en Datos fiscales</Link
            >.
        </p>

        <Form
            v-else
            v-bind="formulario"
            class="flex flex-col gap-4"
            v-slot="{ errors, processing }"
        >
            <input type="hidden" name="emitir" :value="emitir ? '1' : '0'" />

            <Seccion
                titulo="Datos de la factura"
                clase-cuerpo="grid gap-4 sm:grid-cols-2"
            >
                <div class="grid gap-2">
                    <Label for="punto_de_venta_id">Punto de venta</Label>
                    <select
                        id="punto_de_venta_id"
                        name="punto_de_venta_id"
                        :class="claseCampo"
                    >
                        <option
                            v-for="punto in puntosDeVenta"
                            :key="punto.id"
                            :value="punto.id"
                            :selected="punto.id === inicial?.punto_de_venta_id"
                        >
                            {{ punto.etiqueta }}
                        </option>
                    </select>
                    <InputError :message="errors.punto_de_venta_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="fecha_emision">Fecha</Label>
                    <Input
                        id="fecha_emision"
                        name="fecha_emision"
                        type="date"
                        :default-value="inicial?.fecha_emision ?? hoy"
                        required
                    />
                    <InputError :message="errors.fecha_emision" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="cliente_id">Cliente</Label>
                    <select
                        id="cliente_id"
                        v-model="clienteId"
                        name="cliente_id"
                        :class="claseCampo"
                    >
                        <option value="">Consumidor final</option>
                        <option
                            v-for="cliente in clientes"
                            :key="cliente.id"
                            :value="String(cliente.id)"
                        >
                            {{ cliente.etiqueta }}
                        </option>
                    </select>
                    <InputError :message="errors.cliente_id" />
                    <label
                        v-if="emailDelCliente"
                        class="flex items-center gap-2 text-sm"
                    >
                        <input
                            v-model="enviarAlAutorizar"
                            type="checkbox"
                            name="enviar_al_autorizar"
                            value="1"
                            class="size-4 accent-primary"
                            data-test="enviar-al-autorizar"
                        />
                        <span class="wrap-anywhere"
                            >Enviarla a {{ emailDelCliente }} cuando ARCA la
                            autorice</span
                        >
                    </label>
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="concepto">Qué facturás</Label>
                    <select
                        id="concepto"
                        v-model="concepto"
                        name="concepto"
                        :class="claseCampo"
                    >
                        <option
                            v-for="opcion in conceptos"
                            :key="opcion.value"
                            :value="opcion.value"
                        >
                            {{ opcion.etiqueta }}
                        </option>
                    </select>
                    <InputError :message="errors.concepto" />
                </div>

                <template v-if="conServicios">
                    <div class="grid gap-2">
                        <Label for="fecha_servicio_desde">Período desde</Label>
                        <Input
                            id="fecha_servicio_desde"
                            v-model="desde"
                            name="fecha_servicio_desde"
                            type="date"
                        />
                        <InputError :message="errors.fecha_servicio_desde" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="fecha_servicio_hasta">Período hasta</Label>
                        <Input
                            id="fecha_servicio_hasta"
                            v-model="hasta"
                            name="fecha_servicio_hasta"
                            type="date"
                        />
                        <InputError :message="errors.fecha_servicio_hasta" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="fecha_vto_pago">Vencimiento del pago</Label>
                        <Input
                            id="fecha_vto_pago"
                            name="fecha_vto_pago"
                            type="date"
                            :default-value="inicial?.fecha_vto_pago ?? hoy"
                        />
                        <InputError :message="errors.fecha_vto_pago" />
                    </div>
                </template>
            </Seccion>

            <Seccion titulo="Ítems" clase-cuerpo="space-y-4">
                <InputError :message="errors.items" />

                <fieldset
                    v-for="(item, i) in items"
                    :key="i"
                    class="grid grid-cols-2 gap-3 rounded-lg border p-3 sm:grid-cols-[6rem_8rem_1fr]"
                >
                    <legend class="sr-only">Ítem {{ i + 1 }}</legend>
                    <div class="col-span-2 grid gap-1 sm:col-span-3">
                        <Label :for="`descripcion-${i}`">Descripción</Label>
                        <!--
                                Tres líneas como mínimo y crece con el texto
                                (field-sizing). El PDF respeta los saltos.
                            -->
                        <textarea
                            :id="`descripcion-${i}`"
                            v-model="item.descripcion"
                            :name="`items[${i}][descripcion]`"
                            rows="3"
                            maxlength="1000"
                            :class="[
                                claseCampo,
                                'field-sizing-content h-auto min-h-[calc(3lh+1rem)] py-2 leading-normal',
                            ]"
                            @input="editarDescripcion(i)"
                        />
                        <div
                            v-if="i === 0 && plantilla"
                            class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground"
                        >
                            <span v-if="descripcionAutomatica"
                                >Se completa con la descripción habitual del
                                cliente: sigue al período y al total.</span
                            >
                            <span v-else
                                >Editada a mano: el período y el total ya no la
                                cambian.</span
                            >
                            <Button
                                v-if="!descripcionAutomatica"
                                type="button"
                                variant="outline"
                                size="sm"
                                data-test="usar-descripcion-habitual"
                                @click="usarDescripcionHabitual"
                            >
                                <RefreshCw aria-hidden="true" /> Usar la
                                descripción habitual
                            </Button>
                        </div>
                        <p
                            v-else-if="i === 0 && clienteId !== ''"
                            class="text-xs text-muted-foreground"
                        >
                            Este cliente no tiene descripción habitual. Cargala
                            en Clientes y se completa sola con el período y el
                            total de cada factura.
                        </p>
                        <InputError
                            :message="errors[`items.${i}.descripcion`]"
                        />
                    </div>
                    <div class="grid gap-1">
                        <Label :for="`cantidad-${i}`">Cantidad</Label>
                        <Input
                            :id="`cantidad-${i}`"
                            v-model="item.cantidad"
                            :name="`items[${i}][cantidad]`"
                            inputmode="decimal"
                        />
                        <InputError :message="errors[`items.${i}.cantidad`]" />
                    </div>
                    <div class="grid gap-1">
                        <Label :for="`precio-${i}`">Precio unitario</Label>
                        <Input
                            :id="`precio-${i}`"
                            v-model="item.precio_unitario"
                            :name="`items[${i}][precio_unitario]`"
                            inputmode="decimal"
                            placeholder="0,00"
                        />
                        <InputError
                            :message="errors[`items.${i}.precio_unitario`]"
                        />
                    </div>
                    <div
                        class="col-span-2 flex items-end justify-between gap-2 sm:col-span-1 sm:justify-end"
                    >
                        <span class="text-sm tabular-nums"
                            >$
                            {{
                                formatearCentavos(
                                    importeDeItem(
                                        item.cantidad,
                                        item.precio_unitario,
                                    ),
                                )
                            }}</span
                        >
                        <Button
                            v-if="items.length > 1"
                            type="button"
                            variant="ghost"
                            size="icon"
                            :aria-label="`Quitar el ítem ${i + 1}`"
                            @click="items.splice(i, 1)"
                        >
                            <Trash2 aria-hidden="true" />
                        </Button>
                    </div>
                </fieldset>

                <Button
                    type="button"
                    variant="outline"
                    @click="items.push(itemVacio())"
                >
                    <Plus aria-hidden="true" /> Agregar ítem
                </Button>

                <p class="text-right text-lg font-semibold tabular-nums">
                    Total $ {{ formatearCentavos(total) }}
                </p>
                <p
                    v-if="consumidorFinalSobreElTope"
                    class="text-sm text-destructive"
                >
                    Desde $ {{ formatearCentavos(topeConsumidorFinal) }} hay que
                    identificar al comprador: elegí el cliente.
                </p>
            </Seccion>

            <div class="grid gap-2">
                <Label for="observaciones">Observaciones (opcional)</Label>
                <textarea
                    id="observaciones"
                    name="observaciones"
                    rows="2"
                    maxlength="500"
                    :class="[claseCampo, 'h-auto py-2']"
                    :value="inicial?.observaciones ?? ''"
                />
                <InputError :message="errors.observaciones" />
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    :disabled="processing"
                    data-test="guardar-y-emitir"
                    @click="emitir = true"
                >
                    {{ processing && emitir ? 'Pidiendo el CAE…' : 'Emitir' }}
                </Button>
                <Button
                    variant="outline"
                    :disabled="processing"
                    data-test="guardar-borrador"
                    @click="emitir = false"
                >
                    Guardar borrador
                </Button>
            </div>
        </Form>
    </div>
</template>
