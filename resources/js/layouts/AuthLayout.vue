<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import AvisoCookies from '@/components/AvisoCookies.vue';
import { Toaster } from '@/components/ui/sonner';
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();

const esCelular = useMediaQuery('(max-width: 767px)');
</script>

<template>
    <AuthLayout :title="title" :description="description">
        <slot />
    </AuthLayout>
    <AvisoCookies />
    <!-- Mismo lugar que en la app: el error de entrar con Google llega acá. -->
    <Toaster
        :position="esCelular ? 'bottom-center' : 'top-right'"
        :mobile-offset="{
            bottom: 'calc(16px + env(safe-area-inset-bottom))',
        }"
    />
</template>
