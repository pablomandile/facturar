<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { EstadoComprobante } from '@/types/comprobantes';

const props = defineProps<{ estado: EstadoComprobante; etiqueta: string }>();

// El color acompaña al texto, nunca lo reemplaza.
const variante = computed(() => {
    switch (props.estado) {
        case 'autorizado':
            return 'default';
        case 'rechazado':
        case 'error':
            return 'destructive';
        case 'pendiente':
            return 'secondary';
        default:
            return 'outline';
    }
});
</script>

<template>
    <Badge :variant="variante">{{ etiqueta }}</Badge>
</template>
