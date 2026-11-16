<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { CircleCheck, CircleX } from '@lucide/vue';
import { computed } from 'vue';
import ConexionController from '@/actions/App/Http/Controllers/ConexionController';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type PasoDePrueba = { nombre: string; ok: boolean; detalle: string };

defineProps<{ habilitado: boolean }>();

const page = usePage();

// El resultado llega como flash: se ve una vez, después de probar.
const pasos = computed(
    () =>
        (page.flash as { pruebaConexion?: PasoDePrueba[] }).pruebaConexion ??
        [],
);
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-muted-foreground">
            Verifica que los servidores de ARCA respondan, que acepten tu
            certificado y que cada punto de venta esté listo para numerar. No
            emite ningún comprobante.
        </p>

        <Form v-bind="ConexionController.store.form()" v-slot="{ processing }">
            <Button
                :disabled="!habilitado || processing"
                data-test="probar-conexion"
            >
                <Spinner v-if="processing" />
                {{ processing ? 'Probando…' : 'Probar la conexión' }}
            </Button>
        </Form>

        <ul v-if="pasos.length" class="space-y-2" aria-live="polite">
            <li v-for="paso in pasos" :key="paso.nombre" class="flex gap-2">
                <CircleCheck
                    v-if="paso.ok"
                    class="mt-0.5 size-4 shrink-0 text-green-600"
                    aria-hidden="true"
                />
                <CircleX
                    v-else
                    class="mt-0.5 size-4 shrink-0 text-destructive"
                    aria-hidden="true"
                />
                <div class="text-sm">
                    <span class="font-medium">{{ paso.nombre }}</span>
                    <span class="sr-only">{{
                        paso.ok ? '(bien)' : '(con problemas)'
                    }}</span>
                    <p class="text-muted-foreground">{{ paso.detalle }}</p>
                </div>
            </li>
        </ul>
    </div>
</template>
