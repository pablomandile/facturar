<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import AvisoCookies from '@/components/AvisoCookies.vue';
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
            <!-- Solo en la demo: que nadie cargue datos reales en una cuenta compartida. -->
            <p
                v-if="$page.props.demo"
                class="border-b bg-section-tint px-4 py-2 text-sm text-pretty md:px-6"
                data-test="franja-demo"
            >
                Estás en la demo: los datos son inventados, ARCA está simulada
                (las facturas no tienen validez fiscal), los mails no salen y
                todo se borra cada noche.
            </p>
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
        <AvisoCookies />
    </AppShell>
</template>
