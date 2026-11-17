<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import SelectorTamanioTexto from '@/components/SelectorTamanioTexto.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Configuración de apariencia',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Configuración de apariencia" />

    <h1 class="sr-only">Configuración de apariencia</h1>

    <!-- El tamaño de letra va primero: es a lo que viene la mayoría. -->
    <div class="space-y-6">
        <Heading
            variant="small"
            title="Tamaño de la letra"
            description="Se guarda en tu cuenta: vale en todos tus dispositivos"
        />
        <SelectorTamanioTexto />
    </div>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Tema"
            description="Claro, oscuro o el de tu dispositivo"
        />
        <AppearanceTabs />
    </div>
</template>
