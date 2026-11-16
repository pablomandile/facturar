<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ComprobanteController from '@/actions/App/Http/Controllers/ComprobanteController';
import EmisorController from '@/actions/App/Http/Controllers/EmisorController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatearCentavos, importeDeItem } from '@/lib/moneda';
import type {
    ComprobanteEditable,
    ItemDelFormulario,
    Opcion,
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
    puntosDeVenta: Opcion[];
    clientes: Opcion[];
    conceptos: OpcionConcepto[];
    hoy: string;
    inicioDeMes: string;
    finDeMes: string;
    topeConsumidorFinal: number;
    ambiente: string;
}>();

const itemVacio = (): ItemDelFormulario => ({
    descripcion: '',
    cantidad: '1',
    unidad: null,
    precio_unitario: '',
});

const items = ref<ItemDelFormulario[]>(
    props.comprobante?.items.length
        ? props.comprobante.items.map((item) => ({ ...item }))
        : [itemVacio()],
);
const concepto = ref(props.comprobante?.concepto ?? 2);
const clienteId = ref<string>(String(props.comprobante?.cliente_id ?? ''));
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

            <Card>
                <CardHeader
                    ><CardTitle>Datos de la factura</CardTitle></CardHeader
                >
                <CardContent class="grid gap-4 sm:grid-cols-2">
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
                                :selected="
                                    punto.id === comprobante?.punto_de_venta_id
                                "
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
                            :default-value="comprobante?.fecha_emision ?? hoy"
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
                            <Label for="fecha_servicio_desde"
                                >Período desde</Label
                            >
                            <Input
                                id="fecha_servicio_desde"
                                name="fecha_servicio_desde"
                                type="date"
                                :default-value="
                                    comprobante?.fecha_servicio_desde ??
                                    inicioDeMes
                                "
                            />
                            <InputError
                                :message="errors.fecha_servicio_desde"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="fecha_servicio_hasta"
                                >Período hasta</Label
                            >
                            <Input
                                id="fecha_servicio_hasta"
                                name="fecha_servicio_hasta"
                                type="date"
                                :default-value="
                                    comprobante?.fecha_servicio_hasta ??
                                    finDeMes
                                "
                            />
                            <InputError
                                :message="errors.fecha_servicio_hasta"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="fecha_vto_pago"
                                >Vencimiento del pago</Label
                            >
                            <Input
                                id="fecha_vto_pago"
                                name="fecha_vto_pago"
                                type="date"
                                :default-value="
                                    comprobante?.fecha_vto_pago ?? hoy
                                "
                            />
                            <InputError :message="errors.fecha_vto_pago" />
                        </div>
                    </template>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>Ítems</CardTitle></CardHeader>
                <CardContent class="space-y-4">
                    <InputError :message="errors.items" />

                    <fieldset
                        v-for="(item, i) in items"
                        :key="i"
                        class="grid grid-cols-2 gap-3 rounded-lg border p-3 sm:grid-cols-[1fr_6rem_8rem_7.5rem]"
                    >
                        <legend class="sr-only">Ítem {{ i + 1 }}</legend>
                        <div class="col-span-2 grid gap-1 sm:col-span-1">
                            <Label :for="`descripcion-${i}`">Descripción</Label>
                            <Input
                                :id="`descripcion-${i}`"
                                v-model="item.descripcion"
                                :name="`items[${i}][descripcion]`"
                            />
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
                            <InputError
                                :message="errors[`items.${i}.cantidad`]"
                            />
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
                            class="col-span-2 flex items-end justify-between gap-2 sm:col-span-1 sm:flex-col sm:items-end"
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
                        Desde $ {{ formatearCentavos(topeConsumidorFinal) }} hay
                        que identificar al comprador: elegí el cliente.
                    </p>
                </CardContent>
            </Card>

            <div class="grid gap-2">
                <Label for="observaciones">Observaciones (opcional)</Label>
                <textarea
                    id="observaciones"
                    name="observaciones"
                    rows="2"
                    maxlength="500"
                    :class="[claseCampo, 'h-auto py-2']"
                    :value="comprobante?.observaciones ?? ''"
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
