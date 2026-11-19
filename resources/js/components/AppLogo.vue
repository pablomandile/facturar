<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import MarcaFacturar from '@/components/MarcaFacturar.vue';
</script>

<template>
    <!--
        El ícono va envuelto: SidebarMenuButton fuerza a 16 px a todo svg que
        sea hijo directo ([&>svg]:size-4) y el logo quedaba diminuto.
    -->
    <span class="flex size-8 shrink-0 items-center justify-center">
        <AppLogoIcon class="size-8" />
    </span>
    <div class="ml-1 grid flex-1 text-left">
        <MarcaFacturar class="truncate text-base leading-tight" />
    </div>
</template>
