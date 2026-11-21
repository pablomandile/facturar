<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CircleCheck, Circle } from '@lucide/vue';
import EmisorController from '@/actions/App/Http/Controllers/EmisorController';
import CertificadoArca from '@/components/emisor/CertificadoArca.vue';
import DatosFiscales from '@/components/emisor/DatosFiscales.vue';
import EmailDeFacturas from '@/components/emisor/EmailDeFacturas.vue';
import ProbarConexion from '@/components/emisor/ProbarConexion.vue';
import PuntosDeVenta from '@/components/emisor/PuntosDeVenta.vue';
import Seccion from '@/components/Seccion.vue';
import { Badge } from '@/components/ui/badge';
import type {
    Certificado,
    Emisor,
    OpcionAmbiente,
    PasosConfiguracion,
    PlantillaEmail,
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
    email: PlantillaEmail;
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
                        class="size-4 text-emerald-600 dark:text-emerald-400"
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

        <Seccion titulo="1. Tus datos fiscales">
            <p class="mb-4 text-sm text-muted-foreground">
                Los que van impresos en cada factura.
            </p>
            <DatosFiscales
                :emisor="props.emisor"
                :ambientes="props.ambientes"
            />
        </Seccion>

        <Seccion
            titulo="2. Certificado de ARCA"
            tenue
            :class="{ 'opacity-60': !props.emisor }"
        >
            <p class="mb-4 text-sm text-muted-foreground">
                <template v-if="props.emisor"
                    >Para {{ props.emisor.ambiente_etiqueta }}.</template
                >
                <template v-else>Primero guardá tus datos fiscales.</template>
            </p>
            <CertificadoArca
                v-if="props.emisor"
                :ambiente="props.emisor.ambiente"
                :ambiente-etiqueta="props.emisor.ambiente_etiqueta"
                :vigente="props.certificadoVigente"
                :pendiente="props.certificadoPendiente"
            />
        </Seccion>

        <Seccion
            titulo="3. Puntos de venta"
            :class="{ 'opacity-60': !props.emisor }"
        >
            <p class="mb-4 text-sm text-muted-foreground">
                Desde dónde se numeran tus facturas.
            </p>
            <PuntosDeVenta
                v-if="props.emisor"
                :ambiente="props.emisor.ambiente"
                :puntos="props.puntosDeVenta"
            />
        </Seccion>

        <Seccion
            titulo="4. Probar la conexión con ARCA"
            tenue
            :class="{ 'opacity-60': !props.pasos.certificado }"
        >
            <p
                v-if="!props.pasos.certificado"
                class="mb-4 text-sm text-muted-foreground"
            >
                Primero cargá el certificado.
            </p>
            <ProbarConexion
                v-if="props.emisor"
                :habilitado="props.pasos.certificado"
            />
        </Seccion>

        <Seccion v-if="props.emisor" titulo="Email de tus facturas">
            <p class="mb-4 text-sm text-pretty text-muted-foreground">
                El texto con que salen tus facturas por email. Tocá un marcador
                para sumarlo: se reemplaza con los datos de cada factura.
            </p>
            <EmailDeFacturas :email="props.email" />
        </Seccion>
    </div>
</template>
