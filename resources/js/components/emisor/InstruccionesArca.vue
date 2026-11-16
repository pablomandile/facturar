<script setup lang="ts">
import type { Ambiente } from '@/types/emisor';

/**
 * Qué hacer en la web de ARCA con el pedido (CSR). Los caminos son
 * distintos en cada ambiente: en homologación todo pasa por WSASS; en
 * producción hay que pedir el certificado y después autorizarlo.
 */
defineProps<{
    ambiente: Ambiente;
    alias: string;
}>();

const ingresoArca = 'https://auth.afip.gob.ar/contribuyente_/login.xhtml';
</script>

<template>
    <ol
        v-if="ambiente === 'homologacion'"
        class="list-decimal space-y-2 pl-5 text-sm"
    >
        <li>
            Entrá a
            <a
                :href="ingresoArca"
                target="_blank"
                rel="noopener"
                class="underline underline-offset-4"
                >ARCA con tu clave fiscal</a
            >. Si es la primera vez, en el
            <strong>Administrador de Relaciones de Clave Fiscal</strong>
            adherí el servicio
            <strong>WSASS - Autogestión Certificados Homologación</strong>.
        </li>
        <li>
            Abrí <strong>WSASS</strong> y elegí
            <strong>Nuevo Certificado</strong>. Como nombre poné
            <code class="rounded bg-muted px-1">{{ alias }}</code> y pegá el
            contenido del pedido que bajaste (abrilo con el Bloc de notas).
        </li>
        <li>
            Copiá el certificado que te muestra WSASS: es el texto que empieza
            con
            <code class="rounded bg-muted px-1">-----BEGIN CERTIFICATE</code>.
        </li>
        <li>
            Todavía en WSASS, en
            <strong>Crear autorización a servicio</strong>, elegí el certificado
            <code class="rounded bg-muted px-1">{{ alias }}</code> y el servicio
            <strong>wsfe - Facturación Electrónica</strong>.
        </li>
        <li>Pegá el certificado acá abajo.</li>
    </ol>

    <ol v-else class="list-decimal space-y-2 pl-5 text-sm">
        <li>
            Entrá a
            <a
                :href="ingresoArca"
                target="_blank"
                rel="noopener"
                class="underline underline-offset-4"
                >ARCA con tu clave fiscal</a
            >. Si es la primera vez, en el
            <strong>Administrador de Relaciones de Clave Fiscal</strong>
            adherí el servicio
            <strong>Administración de Certificados Digitales</strong>.
        </li>
        <li>
            En <strong>Administración de Certificados Digitales</strong>, agregá
            un alias <code class="rounded bg-muted px-1">{{ alias }}</code
            >, subí el pedido que bajaste y descargá el certificado.
        </li>
        <li>
            En el <strong>Administrador de Relaciones de Clave Fiscal</strong>,
            elegí <strong>Nueva Relación</strong> → <strong>ARCA</strong> →
            <strong>WebServices</strong> →
            <strong>Facturación Electrónica</strong>. Como representante buscá
            el computador fiscal
            <code class="rounded bg-muted px-1">{{ alias }}</code> y confirmá.
        </li>
        <li>Subí el certificado acá abajo.</li>
    </ol>
</template>
