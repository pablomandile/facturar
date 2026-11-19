<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/*
 * El nombre de la app con el "AR" en el celeste del logo (#74acdf, las rayas
 * del comprobante). El nombre sale de config('app.name'); si algún día no
 * termina en "AR", se muestra entero sin resaltar.
 *
 * El celeste sobre blanco tiene poco contraste: por eso va en negrita y solo
 * en la marca, nunca en texto que haya que leer.
 */
const nombre = computed(() => String(usePage().props.name ?? 'FacturAR'));
const partes = computed(() =>
    nombre.value.endsWith('AR')
        ? { base: nombre.value.slice(0, -2), resalto: 'AR' }
        : { base: nombre.value, resalto: '' },
);
</script>

<template>
    <span :aria-label="nombre" class="font-semibold tracking-tight"
        ><span aria-hidden="true">{{ partes.base }}</span
        ><span
            v-if="partes.resalto"
            aria-hidden="true"
            class="font-bold text-marca-celeste"
            >{{ partes.resalto }}</span
        ></span
    >
</template>
