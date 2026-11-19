<script setup lang="ts">
import { computed } from 'vue';
import type { EstadoComprobante } from '@/types/comprobantes';

const props = defineProps<{ estado: EstadoComprobante; etiqueta: string }>();

/*
 * Chips de estado como en el admin de meditarenzn. El color acompaña al
 * texto, nunca lo reemplaza: la etiqueta siempre se lee.
 */
const clases: Record<EstadoComprobante, string> = {
    borrador: 'bg-muted text-muted-foreground',
    pendiente:
        'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    autorizado:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    rechazado: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
    error: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
};

const clase = computed(() => clases[props.estado] ?? clases.borrador);
</script>

<template>
    <span
        :class="[
            'inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap',
            clase,
        ]"
        >{{ etiqueta }}</span
    >
</template>
