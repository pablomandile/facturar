<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { cookies, privacidad, terminos } from '@/routes';

/** Los enlaces a los textos legales: portada, ingreso y pie del menú. */
</script>

<template>
    <nav
        aria-label="Textos legales"
        class="flex flex-wrap justify-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
    >
        <Link :href="terminos()" class="hover:text-foreground hover:underline"
            >Términos</Link
        >
        <Link :href="privacidad()" class="hover:text-foreground hover:underline"
            >Privacidad</Link
        >
        <Link :href="cookies()" class="hover:text-foreground hover:underline"
            >Cookies</Link
        >
    </nav>
</template>
