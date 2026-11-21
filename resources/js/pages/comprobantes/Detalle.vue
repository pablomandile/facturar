<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import ComprobanteController from '@/actions/App/Http/Controllers/ComprobanteController';
import ComprobantePdfController from '@/actions/App/Http/Controllers/ComprobantePdfController';
import EnviarPorEmail from '@/components/comprobantes/EnviarPorEmail.vue';
import EstadoBadge from '@/components/comprobantes/EstadoBadge.vue';
import { Button } from '@/components/ui/button';
import Seccion from '@/components/Seccion.vue';
import { fechaCorta, fechaYHora } from '@/lib/fechas';
import type { ComprobanteDetalle, EmailDeFactura } from '@/types/comprobantes';

const props = defineProps<{
    comprobante: ComprobanteDetalle;
    puede: {
        editar: boolean;
        emitir: boolean;
        borrar: boolean;
        enviar: boolean;
    };
    /** Solo en una autorizada: el diálogo de envío y el historial. */
    email: EmailDeFactura | null;
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

        <Seccion titulo="Receptor" clase-cuerpo="space-y-1 text-sm">
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
        </Seccion>

        <Seccion titulo="Detalle" tenue clase-cuerpo="space-y-3 text-sm">
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
                    <span
                        class="min-w-0 flex-1 wrap-break-word whitespace-pre-line"
                        >{{ item.descripcion }}</span
                    >
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
            <p v-if="comprobante.observaciones" class="text-muted-foreground">
                {{ comprobante.observaciones }}
            </p>
        </Seccion>

        <Seccion
            v-if="email && email.envios.length"
            titulo="Envíos por email"
            clase-cuerpo="p-0 sm:p-0"
        >
            <ul
                class="divide-y text-sm [&>*:nth-child(even)]:bg-section-tint/50"
                data-test="envios"
            >
                <li
                    v-for="envio in email.envios"
                    :key="envio.id"
                    class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2.5"
                >
                    <span
                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="
                            envio.enviado
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                                : 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300'
                        "
                        >{{ envio.enviado ? 'Enviada' : 'No salió' }}</span
                    >
                    <span class="min-w-0 flex-1 wrap-anywhere">
                        {{ envio.destinatarios.join(', ') }}
                        <span
                            v-if="envio.con_copia"
                            class="text-muted-foreground"
                            >(con copia a vos)</span
                        >
                    </span>
                    <span class="text-muted-foreground tabular-nums">{{
                        fechaYHora(envio.fecha)
                    }}</span>
                </li>
            </ul>
        </Seccion>

        <div class="flex flex-wrap gap-2">
            <Button v-if="comprobante.estado === 'autorizado'" as-child>
                <a
                    :href="ComprobantePdfController.show.url(comprobante.id)"
                    download
                    data-test="descargar-pdf"
                    >Descargar PDF</a
                >
            </Button>
            <EnviarPorEmail
                v-if="puede.enviar && email"
                :comprobante-id="comprobante.id"
                :email="email"
            />
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
            <!-- Una factura nueva a partir de esta: la del mes que viene. -->
            <Button variant="outline" as-child>
                <Link
                    :href="ComprobanteController.duplicar(comprobante.id)"
                    data-test="duplicar"
                    >Duplicar</Link
                >
            </Button>
            <Button v-if="puede.borrar" variant="ghost" @click="borrar">
                Borrar
            </Button>
        </div>
    </div>
</template>
