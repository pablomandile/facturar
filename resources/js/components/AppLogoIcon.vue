<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

/**
 * El ícono de FacturAR: el mismo dibujo que resources/marca/icono.svg (de
 * donde salen los PNG de la PWA). Lleva sus propios colores, así que ignora
 * `fill-current` y `text-*` que le pasen los layouts del kit.
 */
defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 512 512"
        role="img"
        aria-label="FacturAR"
        :class="className"
        v-bind="$attrs"
    >
        <rect width="512" height="512" rx="112" fill="#1f4e8c" />
        <path
            d="M148 84h216a12 12 0 0 1 12 12v330l-27-18-27 18-27-18-27 18-27-18-27 18-27-18-27 18-27-18V96a12 12 0 0 1 12-12z"
            fill="#fff"
        />
        <path
            d="M297 170a62 62 0 1 0 0 86"
            fill="none"
            stroke="#1f4e8c"
            stroke-width="30"
            stroke-linecap="round"
        />
        <rect x="176" y="304" width="160" height="18" rx="9" fill="#74acdf" />
        <rect x="176" y="344" width="112" height="18" rx="9" fill="#74acdf" />
    </svg>
</template>
