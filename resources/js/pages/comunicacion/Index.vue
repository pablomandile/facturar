<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ComunicacionController from '@/actions/App/Http/Controllers/ComunicacionController';
import EmailDeFacturas from '@/components/comunicacion/EmailDeFacturas.vue';
import Seccion from '@/components/Seccion.vue';
import type { PlantillaEmail } from '@/types/comunicacion';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Comunicación', href: ComunicacionController.edit() },
        ],
    },
});

defineProps<{ email: PlantillaEmail }>();
</script>

<template>
    <Head title="Comunicación" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <header class="space-y-1">
            <h1 class="text-xl font-semibold tracking-tight">Comunicación</h1>
            <p class="text-sm text-pretty text-muted-foreground">
                Cómo les llegan tus facturas a tus clientes.
            </p>
        </header>

        <Seccion titulo="Email de tus facturas">
            <p class="mb-4 text-sm text-pretty text-muted-foreground">
                El asunto y el texto con que salen tus facturas por email. Tocá
                un marcador para sumarlo donde tengas el cursor: se reemplaza
                con los datos de cada factura.
            </p>
            <EmailDeFacturas :email="email" />
        </Seccion>
    </div>
</template>
