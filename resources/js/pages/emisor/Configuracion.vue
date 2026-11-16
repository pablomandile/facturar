<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CircleCheck, Circle } from '@lucide/vue';
import EmisorController from '@/actions/App/Http/Controllers/EmisorController';
import CertificadoArca from '@/components/emisor/CertificadoArca.vue';
import DatosFiscales from '@/components/emisor/DatosFiscales.vue';
import ProbarConexion from '@/components/emisor/ProbarConexion.vue';
import PuntosDeVenta from '@/components/emisor/PuntosDeVenta.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type {
    Certificado,
    Emisor,
    OpcionAmbiente,
    PasosConfiguracion,
    PuntoDeVenta,
} from '@/types/emisor';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Datos fiscales', href: EmisorController.edit() },
        ],
    },
});

const props = defineProps<{
    emisor: Emisor | null;
    ambientes: OpcionAmbiente[];
    certificadoVigente: Certificado | null;
    certificadoPendiente: Certificado | null;
    puntosDeVenta: PuntoDeVenta[];
    pasos: PasosConfiguracion;
}>();

const resumen = [
    { clave: 'perfil', titulo: 'Datos fiscales' },
    { clave: 'certificado', titulo: 'Certificado de ARCA' },
    { clave: 'punto_de_venta', titulo: 'Punto de venta' },
] as const;
</script>

<template>
    <Head title="Datos fiscales" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <header class="space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-semibold tracking-tight">
                    Datos fiscales
                </h1>
                <Badge v-if="props.emisor" variant="outline">{{
                    props.emisor.ambiente_etiqueta
                }}</Badge>
            </div>
            <ol class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                <li
                    v-for="paso in resumen"
                    :key="paso.clave"
                    class="flex items-center gap-1.5"
                    :class="
                        props.pasos[paso.clave]
                            ? 'text-foreground'
                            : 'text-muted-foreground'
                    "
                >
                    <CircleCheck
                        v-if="props.pasos[paso.clave]"
                        class="size-4 text-green-600"
                        aria-hidden="true"
                    />
                    <Circle v-else class="size-4" aria-hidden="true" />
                    {{ paso.titulo }}
                    <span class="sr-only">{{
                        props.pasos[paso.clave] ? '(listo)' : '(pendiente)'
                    }}</span>
                </li>
            </ol>
        </header>

        <Card>
            <CardHeader>
                <CardTitle>1. Tus datos fiscales</CardTitle>
                <CardDescription>
                    Los que van impresos en cada factura.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <DatosFiscales
                    :emisor="props.emisor"
                    :ambientes="props.ambientes"
                />
            </CardContent>
        </Card>

        <Card :class="{ 'opacity-60': !props.emisor }">
            <CardHeader>
                <CardTitle>2. Certificado de ARCA</CardTitle>
                <CardDescription v-if="props.emisor">
                    Para {{ props.emisor.ambiente_etiqueta }}.
                </CardDescription>
                <CardDescription v-else>
                    Primero guardá tus datos fiscales.
                </CardDescription>
            </CardHeader>
            <CardContent v-if="props.emisor">
                <CertificadoArca
                    :ambiente="props.emisor.ambiente"
                    :ambiente-etiqueta="props.emisor.ambiente_etiqueta"
                    :vigente="props.certificadoVigente"
                    :pendiente="props.certificadoPendiente"
                />
            </CardContent>
        </Card>

        <Card :class="{ 'opacity-60': !props.emisor }">
            <CardHeader>
                <CardTitle>3. Puntos de venta</CardTitle>
                <CardDescription>
                    Desde dónde se numeran tus facturas.
                </CardDescription>
            </CardHeader>
            <CardContent v-if="props.emisor">
                <PuntosDeVenta
                    :ambiente="props.emisor.ambiente"
                    :puntos="props.puntosDeVenta"
                />
            </CardContent>
        </Card>

        <Card :class="{ 'opacity-60': !props.pasos.certificado }">
            <CardHeader>
                <CardTitle>4. Probar la conexión con ARCA</CardTitle>
                <CardDescription v-if="!props.pasos.certificado">
                    Primero cargá el certificado.
                </CardDescription>
            </CardHeader>
            <CardContent v-if="props.emisor">
                <ProbarConexion :habilitado="props.pasos.certificado" />
            </CardContent>
        </Card>
    </div>
</template>
