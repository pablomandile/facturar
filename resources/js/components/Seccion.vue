<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/*
 * Una sección de pantalla con franja celeste de encabezado, como las del admin
 * de meditarenzn (AdminSection). Reemplaza a Card + CardHeader + CardTitle en
 * las pantallas de la app; las tarjetas sueltas (Card) quedan para lo que no
 * es una sección con título.
 *
 * - `titulo` va en la franja; el slot `acciones` se ubica a su derecha.
 * - `tenue` pinta el cuerpo de celeste muy pálido, para alternar secciones.
 */
const props = defineProps<{
    titulo: string;
    tenue?: boolean;
    class?: HTMLAttributes['class'];
    claseCuerpo?: HTMLAttributes['class'];
}>();
</script>

<template>
    <section
        :class="
            cn(
                'overflow-hidden rounded-lg border bg-card text-card-foreground shadow-sm',
                props.class,
            )
        "
    >
        <header
            class="flex flex-wrap items-center justify-between gap-2 bg-section-head px-4 py-2 text-section-head-foreground"
        >
            <h2 class="text-sm font-semibold tracking-wide uppercase">
                {{ titulo }}
            </h2>
            <slot name="acciones" />
        </header>
        <div
            :class="
                cn('p-4 sm:p-5', tenue && 'bg-section-tint', props.claseCuerpo)
            "
        >
            <slot />
        </div>
    </section>
</template>
