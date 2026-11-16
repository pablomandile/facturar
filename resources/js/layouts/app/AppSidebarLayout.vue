<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';
import { useMediaQuery } from '@vueuse/core';

const esCelular = useMediaQuery('(max-width: 767px)');

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <!--
            Abajo en el celular, donde está el pulgar, respetando la barra de
            gestos; arriba a la derecha en escritorio. Es el único overlay que
            DEBE sobrevivir a la navegación: el aviso de un redirect llega
            después de cambiar de página.
        -->
        <Toaster
            :position="esCelular ? 'bottom-center' : 'top-right'"
            :mobile-offset="{
                bottom: 'calc(16px + env(safe-area-inset-bottom))',
            }"
            close-button
        />
    </AppShell>
</template>
