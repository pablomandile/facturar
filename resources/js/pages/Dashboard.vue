<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Circle, CircleCheck, Plus } from '@lucide/vue';
import ComprobanteController from '@/actions/App/Http/Controllers/ComprobanteController';
import EmisorController from '@/actions/App/Http/Controllers/EmisorController';
import Seccion from '@/components/Seccion.vue';
import EstadoBadge from '@/components/comprobantes/EstadoBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { fechaCorta } from '@/lib/fechas';
import { dashboard } from '@/routes';
import type { FilaComprobante } from '@/types/comprobantes';
import type { PasosConfiguracion } from '@/types/emisor';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Panel', href: dashboard() }],
    },
});

defineProps<{
    configurado: boolean;
    pasos: PasosConfiguracion;
    ambiente?: string;
    mes?: { nombre: string; total: string; cantidad: number };
    certificado?: { vence: string | null; dias: number | null } | null;
    atencion?: { pendientes: number; con_problemas: number };
    ultimas?: Omit<FilaComprobante, 'tiene_numero'>[];
}>();

const titulos: Record<keyof PasosConfiguracion, string> = {
    perfil: 'Cargá tus datos fiscales',
    certificado: 'Conseguí el certificado de ARCA',
    punto_de_venta: 'Cargá un punto de venta',
};
</script>

<template>
    <Head title="Panel" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
        <header class="flex flex-wrap items-center gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Panel</h1>
                <p v-if="ambiente" class="text-sm text-muted-foreground">
                    {{ ambiente }}
                </p>
            </div>
            <Button v-if="configurado" class="ml-auto" as-child>
                <Link :href="ComprobanteController.create()">
                    <Plus aria-hidden="true" /> Nueva factura
                </Link>
            </Button>
        </header>

        <Seccion
            v-if="!configurado"
            titulo="Antes de facturar"
            tenue
            clase-cuerpo="space-y-4"
        >
            <p class="text-sm text-muted-foreground">
                Tres pasos, una sola vez. Te guiamos en cada uno.
            </p>
            <ol class="space-y-2">
                <li
                    v-for="(hecho, paso) in pasos"
                    :key="paso"
                    class="flex items-center gap-2 text-sm"
                >
                    <CircleCheck
                        v-if="hecho"
                        class="size-4 text-emerald-600 dark:text-emerald-400"
                        aria-hidden="true"
                    />
                    <Circle v-else class="size-4" aria-hidden="true" />
                    <span :class="{ 'text-muted-foreground': hecho }">{{
                        titulos[paso]
                    }}</span>
                    <span class="sr-only">{{
                        hecho ? '(listo)' : '(pendiente)'
                    }}</span>
                </li>
            </ol>
            <Button as-child>
                <Link :href="EmisorController.edit()">Seguir</Link>
            </Button>
        </Seccion>

        <template v-if="mes">
            <div class="grid gap-4 sm:grid-cols-2">
                <Card class="border-l-4 border-l-primary">
                    <CardHeader>
                        <CardDescription class="first-letter:uppercase"
                            >Facturado en {{ mes.nombre }}</CardDescription
                        >
                        <CardTitle class="text-2xl tabular-nums"
                            >$ {{ mes.total }}</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="text-sm text-muted-foreground">
                        {{ mes.cantidad }}
                        {{
                            mes.cantidad === 1
                                ? 'factura autorizada'
                                : 'facturas autorizadas'
                        }}
                    </CardContent>
                </Card>

                <Card v-if="certificado" class="border-l-4 border-l-primary">
                    <CardHeader>
                        <CardDescription>Certificado de ARCA</CardDescription>
                        <CardTitle
                            class="text-base"
                            :class="{
                                'text-destructive':
                                    certificado.dias !== null &&
                                    certificado.dias <= 30,
                            }"
                        >
                            Vence el {{ fechaCorta(certificado.vence) }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm text-muted-foreground">
                        <template
                            v-if="
                                certificado.dias !== null &&
                                certificado.dias <= 30
                            "
                        >
                            Faltan {{ certificado.dias }} días.
                            <Link
                                :href="EmisorController.edit()"
                                class="underline underline-offset-4"
                                >Renovalo</Link
                            >.
                        </template>
                        <template v-else>Todo en orden.</template>
                    </CardContent>
                </Card>
            </div>

            <p
                v-if="
                    atencion && (atencion.pendientes || atencion.con_problemas)
                "
                class="rounded-lg border border-destructive/50 p-4 text-sm"
            >
                <template v-if="atencion.con_problemas">
                    {{ atencion.con_problemas }}
                    {{
                        atencion.con_problemas === 1
                            ? 'factura quedó'
                            : 'facturas quedaron'
                    }}
                    rechazada o con error.
                    <Link
                        :href="ComprobanteController.index()"
                        class="underline underline-offset-4"
                        >Revisalas</Link
                    >.
                </template>
                <template v-if="atencion.pendientes">
                    {{ atencion.pendientes }} esperando confirmación de ARCA.
                </template>
            </p>

            <Seccion titulo="Últimas facturas" clase-cuerpo="p-0 sm:p-0">
                <p
                    v-if="!ultimas?.length"
                    class="p-4 text-sm text-muted-foreground"
                >
                    Todavía no hiciste ninguna.
                </p>
                <ul
                    v-else
                    class="divide-y [&>*:nth-child(even)]:bg-section-tint/50"
                >
                    <li v-for="fila in ultimas" :key="fila.id">
                        <Link
                            :href="ComprobanteController.show(fila.id)"
                            class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2.5 hover:bg-accent"
                        >
                            <!--
                                El nombre arriba y el número abajo: en una sola
                                línea, a 390 px el nombre quedaba en "M…".
                            -->
                            <span class="min-w-0 flex-1">
                                <span class="block truncate">{{
                                    fila.receptor
                                }}</span>
                                <span
                                    class="block font-mono text-xs text-muted-foreground"
                                    >{{ fila.numero }} ·
                                    {{ fechaCorta(fila.fecha) }}</span
                                >
                            </span>
                            <span class="flex items-center gap-2">
                                <span class="tabular-nums"
                                    >$ {{ fila.total }}</span
                                >
                                <EstadoBadge
                                    :estado="fila.estado"
                                    :etiqueta="fila.estado_etiqueta"
                                />
                            </span>
                        </Link>
                    </li>
                </ul>
            </Seccion>
        </template>
    </div>
</template>
