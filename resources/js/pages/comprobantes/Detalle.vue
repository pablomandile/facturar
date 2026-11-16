<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import ComprobanteController from '@/actions/App/Http/Controllers/ComprobanteController';
import EstadoBadge from '@/components/comprobantes/EstadoBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { fechaCorta } from '@/lib/fechas';
import type { ComprobanteDetalle } from '@/types/comprobantes';

const props = defineProps<{
    comprobante: ComprobanteDetalle;
    puede: { editar: boolean; emitir: boolean; borrar: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Facturas', href: ComprobanteController.index() },
        ],
    },
});

function borrar(): void {
    router.delete(ComprobanteController.destroy.url(props.comprobante.id), {
        onBefore: () => confirm('¿Borrar este borrador?'),
    });
}
</script>

<template>
    <Head :title="`${comprobante.tipo} ${comprobante.numero}`" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
        <header class="flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-semibold tracking-tight">
                {{ comprobante.tipo }}
                <span class="font-mono">{{ comprobante.numero }}</span>
            </h1>
            <EstadoBadge
                :estado="comprobante.estado"
                :etiqueta="comprobante.estado_etiqueta"
            />
            <span class="text-sm text-muted-foreground">{{
                comprobante.ambiente
            }}</span>
        </header>

        <section
            v-if="comprobante.estado === 'autorizado'"
            class="rounded-lg border border-green-600/40 p-4"
        >
            <p class="text-sm text-muted-foreground">CAE</p>
            <p class="font-mono text-lg">{{ comprobante.cae }}</p>
            <p class="text-sm text-muted-foreground">
                Vence el {{ fechaCorta(comprobante.cae_vencimiento) }}
            </p>
        </section>

        <section
            v-if="
                comprobante.respuesta_arca &&
                comprobante.estado !== 'autorizado'
            "
            class="space-y-1 rounded-lg border border-destructive/50 p-4 text-sm"
            aria-live="polite"
        >
            <p class="font-medium">Lo que contestó ARCA</p>
            <p v-if="comprobante.respuesta_arca.mensaje">
                {{ comprobante.respuesta_arca.mensaje }}
            </p>
            <ul class="list-inside list-disc">
                <li
                    v-for="obs in [
                        ...(comprobante.respuesta_arca.observaciones ?? []),
                        ...(comprobante.respuesta_arca.errores ?? []),
                    ]"
                    :key="obs.codigo"
                >
                    {{ obs.mensaje }}
                    <span class="text-muted-foreground"
                        >({{ obs.codigo }})</span
                    >
                </li>
            </ul>
        </section>

        <p
            v-if="comprobante.estado === 'pendiente'"
            class="rounded-lg border p-4 text-sm"
        >
            Se mandó a ARCA y no llegó la respuesta. Lo estamos verificando: en
            unos minutos queda autorizada o con error.
        </p>

        <Card>
            <CardHeader><CardTitle>Receptor</CardTitle></CardHeader>
            <CardContent class="space-y-1 text-sm">
                <p class="font-medium">{{ comprobante.receptor.nombre }}</p>
                <p v-if="comprobante.receptor.documento">
                    {{ comprobante.receptor.documento }}
                </p>
                <p class="text-muted-foreground">
                    {{ comprobante.receptor.condicion_iva }}
                </p>
                <p v-if="comprobante.receptor.domicilio">
                    {{ comprobante.receptor.domicilio }}
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Detalle</CardTitle></CardHeader>
            <CardContent class="space-y-3 text-sm">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-1">
                    <dt class="text-muted-foreground">Fecha</dt>
                    <dd>{{ fechaCorta(comprobante.fecha_emision) }}</dd>
                    <dt class="text-muted-foreground">Concepto</dt>
                    <dd>{{ comprobante.concepto }}</dd>
                    <template v-if="comprobante.fecha_servicio_desde">
                        <dt class="text-muted-foreground">Período</dt>
                        <dd>
                            {{ fechaCorta(comprobante.fecha_servicio_desde) }}
                            al
                            {{ fechaCorta(comprobante.fecha_servicio_hasta) }}
                        </dd>
                        <dt class="text-muted-foreground">Vence el pago</dt>
                        <dd>{{ fechaCorta(comprobante.fecha_vto_pago) }}</dd>
                    </template>
                </dl>

                <ul class="divide-y border-y">
                    <li
                        v-for="(item, i) in comprobante.items"
                        :key="i"
                        class="flex flex-wrap justify-between gap-x-4 py-2"
                    >
                        <span class="min-w-0 flex-1">{{
                            item.descripcion
                        }}</span>
                        <span class="text-muted-foreground tabular-nums"
                            >{{ item.cantidad }} × $
                            {{ item.precio_unitario }}</span
                        >
                        <span class="w-28 text-right tabular-nums"
                            >$ {{ item.importe }}</span
                        >
                    </li>
                </ul>

                <p class="text-right text-lg font-semibold tabular-nums">
                    Total $ {{ comprobante.total }}
                </p>
                <p
                    v-if="comprobante.observaciones"
                    class="text-muted-foreground"
                >
                    {{ comprobante.observaciones }}
                </p>
            </CardContent>
        </Card>

        <div class="flex flex-wrap gap-2">
            <Form
                v-if="puede.emitir"
                v-bind="ComprobanteController.emitir.form(comprobante.id)"
                v-slot="{ processing }"
            >
                <Button :disabled="processing" data-test="emitir">
                    {{ processing ? 'Pidiendo el CAE…' : 'Emitir' }}
                </Button>
            </Form>
            <Button v-if="puede.editar" variant="outline" as-child>
                <Link :href="ComprobanteController.edit(comprobante.id)"
                    >Editar</Link
                >
            </Button>
            <Button v-if="puede.borrar" variant="ghost" @click="borrar">
                Borrar
            </Button>
        </div>
    </div>
</template>
